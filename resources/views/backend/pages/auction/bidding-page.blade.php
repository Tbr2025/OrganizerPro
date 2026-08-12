@extends('backend.layouts.app')
@section('title', 'Live Auction — ' . $userTeam->name)

@push('styles')
<style>
    .bidding-wrapper {
        background: #030712;
        color: #fff;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }
    .bidding-wrapper.is-fullscreen {
        position: fixed;
        inset: 0;
        z-index: 9999;
    }
    .dot-bg {
        background-image: radial-gradient(circle, rgba(255,255,255,0.04) 1px, transparent 1px);
        background-size: 20px 20px;
    }

    @keyframes timerPulse { 0%,100% { opacity:1; } 50% { opacity:0.3; } }
    .timer-critical { animation: timerPulse 0.5s ease-in-out infinite; }

    /* Closing call — punches in as each call fires. */
    @keyframes finalCallPulse {
        0%   { transform: scale(0.9); opacity: 0; }
        40%  { transform: scale(1.03); opacity: 1; }
        100% { transform: scale(1); }
    }
    .final-call-pulse { animation: finalCallPulse 0.7s cubic-bezier(0.34,1.56,0.64,1) both; }

    @keyframes soldStamp {
        0% { transform: rotate(-12deg) scale(0); opacity:0; }
        60% { transform: rotate(-12deg) scale(1.2); opacity:1; }
        100% { transform: rotate(-12deg) scale(1); opacity:1; }
    }
    .sold-stamp { animation: soldStamp 0.4s ease-out forwards; }

    @keyframes unsoldStamp {
        0% { transform: rotate(-12deg) scale(0); opacity:0; }
        100% { transform: rotate(-12deg) scale(1); opacity:1; }
    }
    .unsold-stamp { animation: unsoldStamp 0.4s ease-out forwards; }

    @keyframes bidFlash { 0% { transform: scale(1.06); } 100% { transform: scale(1); } }
    .bid-flash { animation: bidFlash 0.25s ease-out; }

    @keyframes liveBlink { 0%,100% { opacity:1; } 50% { opacity:0.3; } }
    .live-dot { animation: liveBlink 1s ease-in-out infinite; }

    @keyframes raiseGlow {
        0%,100% { box-shadow: 0 0 16px rgba(34,197,94,0.25); }
        50% { box-shadow: 0 0 32px rgba(34,197,94,0.5), 0 0 48px rgba(34,197,94,0.15); }
    }
    .raise-glow { animation: raiseGlow 1.5s ease-in-out infinite; }

    .bidding-wrapper ::-webkit-scrollbar { width: 4px; }
    .bidding-wrapper ::-webkit-scrollbar-track { background: transparent; }
    .bidding-wrapper ::-webkit-scrollbar-thumb { background: #374151; border-radius: 4px; }
</style>
@endpush

@section('admin-content')
<div class="bidding-wrapper rounded-lg overflow-hidden"
     :class="{ 'is-fullscreen': isFullscreen }"
     x-data="teamBiddingPanel()"
     x-init="init()"
     @keydown.window="handleKeydown($event)"
     id="biddingWrapper">

    <div class="flex h-[calc(100vh-120px)]" :class="isFullscreen ? '!h-screen' : ''">

        {{-- ═══════════════════════ MAIN AREA ═══════════════════════ --}}
        <div class="flex-1 flex flex-col min-w-0 dot-bg">

            {{-- ── TOP BAR ── --}}
            <div class="flex-shrink-0 bg-gray-900/90 backdrop-blur-sm border-b border-gray-800/60 px-4 py-2.5 flex items-center justify-between">
                {{-- Left: Team identity --}}
                <div class="flex items-center gap-2.5 min-w-0">
                    {{-- team_logo_url, not logo_path: the latter is not a column on
                         ActualTeam, so the manager's own team badge never appeared. --}}
                    @if($userTeam->team_logo_url)
                        <img src="{{ $userTeam->team_logo_url }}" alt="{{ $userTeam->name }}" class="w-8 h-8 rounded-full object-cover ring-2 ring-gray-700 flex-shrink-0">
                    @else
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-cyan-500 to-blue-600 flex items-center justify-center text-white font-bold text-[11px] ring-2 ring-gray-700 flex-shrink-0">
                            {{ substr($userTeam->name, 0, 2) }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <div class="text-[13px] font-semibold text-white truncate leading-tight">{{ $userTeam->name }}</div>
                        <div class="text-[10px] text-gray-500 truncate leading-tight">{{ $auction->name }}</div>
                    </div>
                </div>

                {{-- Center: Status badges --}}
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <div x-show="auctionStatus !== 'completed'" class="flex items-center gap-1 bg-red-500/15 border border-red-500/25 px-2 py-0.5 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 live-dot"></span>
                        <span class="text-[10px] font-bold text-red-400 uppercase tracking-wide">Live</span>
                    </div>
                    <span class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded-full tracking-wide"
                          :class="{
                              'bg-blue-500/15 text-blue-400 border border-blue-500/25': bidType === 'open' && auctionMode !== 'offline',
                              'bg-purple-500/15 text-purple-400 border border-purple-500/25': bidType === 'closed',
                              'bg-orange-500/15 text-orange-400 border border-orange-500/25': auctionMode === 'offline'
                          }"
                          x-text="auctionMode === 'offline' ? 'OFFLINE' : (bidType === 'closed' ? 'SEALED' : 'OPEN')"></span>
                </div>

                {{-- Right: Budget + Controls --}}
                <div class="flex items-center gap-3 flex-shrink-0">
                    <div class="text-right">
                        <div class="text-[9px] text-gray-500 uppercase tracking-wider leading-none">Budget</div>
                        <div class="text-sm font-bold leading-tight"
                             :class="teamBudget < teamPurse * 0.2 ? 'text-red-400' : 'text-emerald-400'"
                             x-text="formatCurrency(teamBudget)"></div>
                    </div>
                    <button @click="toggleFullscreen()" class="w-7 h-7 rounded-md bg-gray-800 hover:bg-gray-700 flex items-center justify-center text-gray-400 hover:text-white transition" title="Fullscreen (F)">
                        <svg x-show="!isFullscreen" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5"/></svg>
                        <svg x-show="isFullscreen" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9L4 4m0 0v4m0-4h4m7 5l5-5m0 0v4m0-4h-4M9 15l-5 5m0 0v-4m0 4h4m7-5l5 5m0 0v-4m0 4h-4"/></svg>
                    </button>
                    @if(isset($isPreviewMode) && $isPreviewMode)
                        <a href="{{ route('team.auction.bidding.show', $auction->id) }}" class="w-7 h-7 rounded-md bg-yellow-500/20 hover:bg-yellow-500/30 flex items-center justify-center text-yellow-400 transition" title="Switch Team">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        </a>
                    @endif
                </div>
            </div>

            {{-- Timer progress bar --}}
            <div x-show="timerSeconds > 0 || timerExpired" class="h-0.5 bg-gray-800/50 flex-shrink-0">
                <div class="h-full transition-all duration-1000 ease-linear rounded-r"
                     :class="timerExpired ? 'bg-red-500' : (timerSeconds <= 5 ? 'bg-red-500' : 'bg-cyan-500')"
                     :style="`width: ${timerWidth}%`"></div>
            </div>

            {{-- ── MAIN STAGE ── --}}
            <div class="flex-1 flex items-center justify-center p-6 overflow-hidden relative">

                {{-- WAITING STATE --}}
                <div x-show="state === 'waiting'" x-transition class="text-center">
                    <div class="w-16 h-16 mx-auto mb-3 rounded-full border-2 border-dashed border-gray-700/60 flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-500 mb-1">Waiting for Next Player</h2>
                    <p class="text-gray-600 text-xs">{{ $auction->name }}</p>
                    <div class="flex justify-center gap-1.5 mt-3">
                        <div class="w-1.5 h-1.5 rounded-full bg-cyan-500 animate-pulse"></div>
                        <div class="w-1.5 h-1.5 rounded-full bg-cyan-500 animate-pulse" style="animation-delay:0.2s"></div>
                        <div class="w-1.5 h-1.5 rounded-full bg-cyan-500 animate-pulse" style="animation-delay:0.4s"></div>
                    </div>
                </div>

                {{-- COMPLETED STATE --}}
                <div x-show="state === 'completed'" x-transition x-cloak class="text-center">
                    <div class="text-5xl mb-3">🏆</div>
                    <h1 class="text-2xl font-bold text-yellow-400 mb-1">Auction Completed</h1>
                    <p class="text-gray-500 text-sm">Thank you for participating!</p>
                </div>

                {{-- BIDDING STATE --}}
                <div x-show="state === 'bidding'" x-transition x-cloak class="w-full max-w-lg mx-auto">

                    {{-- Player card --}}
                    <div class="flex items-start gap-4 mb-4">
                        <div class="w-20 h-20 rounded-lg overflow-hidden ring-2 ring-gray-700/60 bg-gray-800 flex-shrink-0">
                            <template x-if="player.image_url && !player.image_url.includes('ui-avatars')">
                                <img :src="player.image_url" class="w-full h-full object-cover object-top" :alt="player.name">
                            </template>
                            <template x-if="!player.image_url || player.image_url.includes('ui-avatars')">
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-orange-500 to-orange-700 text-xl font-bold text-white"
                                     x-text="player.name?.substring(0,2).toUpperCase() || 'P'"></div>
                            </template>
                        </div>
                        <div class="min-w-0 flex-1 pt-0.5">
                            <h1 class="text-lg font-bold tracking-tight truncate leading-tight" x-text="player.name"></h1>
                            <div class="flex items-center gap-1 flex-wrap mt-1.5">
                                <span class="px-1.5 py-px bg-gray-800/80 border border-gray-700/60 rounded text-[10px] text-gray-300 font-medium" x-text="player.role"></span>
                                <span x-show="player.batting_style && player.batting_style !== 'N/A'" class="px-1.5 py-px bg-gray-800/80 border border-gray-700/60 rounded text-[10px] text-gray-500" x-text="player.batting_style"></span>
                                <span x-show="player.bowling_style && player.bowling_style !== 'N/A'" class="px-1.5 py-px bg-gray-800/80 border border-gray-700/60 rounded text-[10px] text-gray-500" x-text="player.bowling_style"></span>
                            </div>
                            {{-- Player Stats --}}
                            <div x-show="player.total_matches != null || player.total_runs != null || player.total_wickets != null" class="flex items-center gap-2 mt-1.5">
                                <template x-if="player.total_matches != null">
                                    <span class="text-[10px] text-gray-400"><span class="font-bold text-blue-400" x-text="player.total_matches"></span> M</span>
                                </template>
                                <template x-if="player.total_runs != null">
                                    <span class="text-[10px] text-gray-400"><span class="font-bold text-amber-400" x-text="player.total_runs"></span> R</span>
                                </template>
                                <template x-if="player.total_wickets != null">
                                    <span class="text-[10px] text-gray-400"><span class="font-bold text-emerald-400" x-text="player.total_wickets"></span> W</span>
                                </template>
                            </div>
                            <div class="mt-1.5 flex items-center gap-1.5">
                                <span class="text-[10px] text-cyan-500/80 uppercase tracking-wider font-medium">Base</span>
                                <span class="text-sm font-bold text-cyan-400" x-text="formatCurrency(player.base_price)"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Current Bid Panel --}}
                    <div class="bg-gray-900/60 border border-gray-800/60 rounded-lg p-4 mb-3 text-center">
                        {{-- Closing call: the same escalation the organizer and the big
                             screen show, so a bidder knows exactly how long is left. --}}
                        <template x-if="finalCall && !timerExpired">
                            <div class="mb-2 -mx-4 -mt-4 px-4 py-2 rounded-t-lg final-call-pulse"
                                 :class="finalCall.is_final ? 'bg-red-600' : 'bg-amber-500'">
                                <p class="text-sm font-black tracking-[0.2em] uppercase"
                                   :class="finalCall.is_final ? 'text-white' : 'text-black'"
                                   x-text="finalCall.label"></p>
                            </div>
                        </template>

                        {{-- Timer — the OPEN round's clock.
                             Hidden once a sealed round is collecting: the open clock has expired by
                             definition (that expiry is what opens the sealed round), so it sat there
                             reading TIME UP over a sealed round that had just started and had a
                             clock of its own further down the page. Two clocks, one of them wrong.
                             The sealed countdown is the one that matters then. --}}
                        <div x-show="(timerSeconds > 0 || timerExpired) && !(sealed.active && sealed.state === 'collecting')" class="mb-2">
                            <span class="font-bold font-mono"
                                  :class="timerExpired
                                    ? 'text-red-500 text-base'
                                    : (finalCall ? (finalCall.is_final ? 'text-red-400 text-3xl timer-critical' : 'text-amber-300 text-2xl') : (timerSeconds <= 5 ? 'text-red-500 text-base timer-critical' : 'text-white text-base'))"
                                  x-text="timerExpired ? 'TIME UP' : timerSeconds + 's'"></span>
                        </div>

                        <div class="text-[9px] uppercase tracking-[0.15em] text-gray-500 mb-1">Current Bid</div>
                        <div class="text-3xl font-black text-emerald-400 leading-none"
                             :class="bidJustChanged ? 'bid-flash' : ''"
                             x-text="formatCurrency(player.current_price)"></div>

                        <template x-if="player.current_bid_team">
                            <div class="flex items-center justify-center gap-1.5 mt-2">
                                <span class="text-[11px] text-gray-500">by</span>
                                <span class="text-[11px] font-semibold"
                                      :class="isMyTeamHighest ? 'text-green-400' : 'text-gray-300'"
                                      x-text="player.current_bid_team.name"></span>
                                <span x-show="isMyTeamHighest" class="px-1 py-px bg-green-500 text-white text-[8px] rounded font-bold uppercase leading-none">You</span>
                            </div>
                        </template>

                        {{-- The team's own SEALED amount, from the sealed entry.
                             This read `myBidAmount` — the team's last OPEN bid on this player — and
                             labelled it "Your Sealed Bid" whenever bidding had gone closed. So a
                             team that had raised 4.1M in the open round, and had entered nothing at
                             all in the sealed one, was shown 4.1M as its sealed bid. The only
                             honest source is the entry itself, which is null until they submit. --}}
                        <div x-show="bidType === 'closed' && sealed.my_entry?.amount" class="mt-2.5 pt-2.5 border-t border-gray-800/60">
                            <div class="text-[9px] text-gray-500 uppercase tracking-wider">Your Sealed Bid</div>
                            <div class="text-sm font-bold text-cyan-400 mt-0.5"
                                 x-text="sealedAmountHidden ? '••••••' : formatCurrency(sealed.my_entry?.amount)"></div>
                        </div>
                    </div>

                    {{-- OPEN BID CONTROLS --}}
                    <div x-show="bidType === 'open' && auctionMode !== 'offline'">
                        <div x-show="nextBidAmount > 0" class="text-center mb-2">
                            <span class="text-[10px] text-gray-500 uppercase tracking-wider">Your Bid Will Be</span>
                            <div class="text-sm font-bold text-cyan-400 mt-0.5" x-text="formatCurrency(nextBidAmount)"></div>
                        </div>

                        <button @click="raiseHand()"
                                :disabled="!canRaiseHand"
                                class="w-full py-3 rounded-lg font-bold text-sm transition-all"
                                :class="isMyTeamHighest
                                    ? 'bg-blue-600/20 border border-blue-500/40 text-blue-400 cursor-default'
                                    : (canRaiseHand
                                        ? 'bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white shadow-lg shadow-green-500/20 raise-glow'
                                        : 'bg-gray-800/60 border border-gray-700/40 text-gray-500 cursor-not-allowed')">
                            <span x-show="!isSubmitting">
                                <span x-show="isMyTeamHighest">YOU ARE HIGHEST BIDDER</span>
                                <span x-show="!isMyTeamHighest && canRaiseHand">RAISE HAND</span>
                                <span x-show="!isMyTeamHighest && !canRaiseHand && timerExpired">TIME EXPIRED</span>
                                <span x-show="!isMyTeamHighest && !canRaiseHand && !timerExpired && nextBidAmount > maxBidAllowed">BUDGET EXCEEDED</span>
                                <span x-show="!isMyTeamHighest && !canRaiseHand && !timerExpired && nextBidAmount <= maxBidAllowed && nextBidAmount <= 0">WAITING...</span>
                            </span>
                            <span x-show="isSubmitting">Placing Bid...</span>
                        </button>

                        <p x-show="canRaiseHand && !isMyTeamHighest" class="text-center text-gray-600 text-[10px] mt-2">
                            Press <kbd class="px-1 py-px bg-gray-800 rounded text-gray-400 font-mono text-[9px]">Space</kbd> or <kbd class="px-1 py-px bg-gray-800 rounded text-gray-400 font-mono text-[9px]">Enter</kbd> to bid
                        </p>
                    </div>

                    {{-- SEALED (CLOSED) ROUND --}}
                    {{-- No `auctionMode !== 'offline'` here.
                         Offline describes OPEN bidding — the organizer calls the room aloud — and
                         the open controls below are still hidden for it. A sealed round is a
                         single private number, which is exactly what a manager should type on
                         their own device even in a room-called auction. --}}
                    <div x-show="bidType === 'closed'" class="space-y-2.5">

                        {{-- Waiting for the organizer to open the round --}}
                        <template x-if="!sealed.active || sealed.state === 'pending'">
                            <div class="bg-purple-500/10 border border-purple-500/25 rounded-lg p-3.5 text-center">
                                <div class="text-purple-300 text-xs font-bold uppercase tracking-wider mb-1">Sealed Round</div>
                                <p class="text-gray-400 text-[11px]">
                                    Open bidding has closed for this player. Waiting for the organizer.
                                </p>
                            </div>
                        </template>

                        {{-- Left out of this round, or the round is over.
                             The organizer chooses which teams take part, so say so plainly rather
                             than showing an entry form the server would refuse. But a FINISHED
                             round said the same thing — so a manager whose round had simply ended
                             was told their team was excluded from it, which is a different and
                             more alarming statement. The two are separated. --}}
                        <template x-if="sealed.active && sealed.invited === false">
                            <div class="bg-gray-900/60 border border-gray-800/60 rounded-lg p-3.5 text-center">
                                <div class="text-gray-400 text-xs font-bold uppercase tracking-wider mb-1">Sealed Round</div>
                                <p class="text-gray-500 text-[11px]"
                                   x-text="['no_entries','awarded','abandoned','revealed'].includes(sealed.state)
                                        ? 'This player\'s sealed round has finished. Waiting for the organizer.'
                                        : 'This round is between the teams the organizer selected.'"></p>
                            </div>
                        </template>

                        {{-- The conditions, and the decision to enter --}}
                        {{-- Reachable in COLLECTING too, not only entry_open.
                             The organizer can press Start while a team is still `invited`, and the
                             accept panel only rendered for entry_open — so that team was left with
                             no way to accept AND no bid box: a dead end while the clock ran. --}}
                        <template x-if="sealed.active
                                        && sealed.invited !== false
                                        && ['entry_open','collecting'].includes(sealed.state)
                                        && ['invited','may_opt_in'].includes(sealedEntryState)">
                            <div class="bg-gray-900/60 border border-purple-500/25 rounded-lg p-3.5">
                                <div class="text-purple-300 text-xs font-bold uppercase tracking-wider mb-2.5 text-center">Enter Sealed Round</div>

                                <div class="space-y-1.5 text-[11px]">
                                    <div class="flex justify-between"><span class="text-gray-400">Purse remaining</span>
                                        <span class="text-white font-semibold" x-text="formatCurrency(sealed.ceilings?.remaining_budget)"></span></div>
                                    <div class="flex justify-between"><span class="text-gray-400">Places still to fill</span>
                                        <span class="text-white font-semibold" x-text="sealed.ceilings?.slots_remaining ?? '—'"></span></div>
                                    <div class="flex justify-between"><span class="text-gray-400">Held back for them</span>
                                        <span class="text-amber-400 font-semibold" x-text="formatCurrency(sealed.ceilings?.reserve_amount)"></span></div>
                                </div>

                                {{-- Both ceilings, with the binding one marked. A team at the
                                     per-player wall needs to know it is the rule, not that
                                     it is broke. --}}
                                <div class="mt-2.5 pt-2.5 border-t border-gray-800/60 space-y-1.5 text-[11px]">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-400">
                                            Per-player cap
                                            <span class="text-gray-600" x-text="'(' + (sealed.ceilings?.per_player_cap_pct ?? 70) + '% of ' + formatCurrency(sealed.ceilings?.allocated) + ')'"></span>
                                        </span>
                                        <span :class="sealedCapBinds ? 'text-amber-300 font-bold' : 'text-gray-300'"
                                              x-text="formatCurrency(sealed.ceilings?.per_player_cap)"></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-400">Squad-reserve maximum</span>
                                        <span :class="!sealedCapBinds ? 'text-amber-300 font-bold' : 'text-gray-300'"
                                              x-text="formatCurrency(sealed.ceilings?.reserve_max)"></span>
                                    </div>
                                    <div class="flex justify-between items-center pt-1">
                                        <span class="text-white font-bold text-xs">Your maximum bid</span>
                                        <span class="text-emerald-400 font-bold text-sm" x-text="formatCurrency(sealedCeiling)"></span>
                                    </div>
                                </div>

                                <div class="flex gap-2 mt-3">
                                    <button @click="sealedAccept()" :disabled="isSubmitting"
                                            class="flex-1 py-2.5 rounded-lg font-bold text-xs bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white transition">
                                        I ACCEPT
                                    </button>
                                    <button @click="sealedDecline()" :disabled="isSubmitting"
                                            class="px-4 py-2.5 rounded-lg font-bold text-xs bg-gray-800/60 border border-gray-700/40 text-gray-400 hover:text-gray-200 transition">
                                        WITHDRAW
                                    </button>
                                </div>
                            </div>
                        </template>

                        {{-- Withdrawn --}}
                        <template x-if="sealed.active && sealedEntryState === 'withdrawn'">
                            <div class="bg-gray-900/60 border border-gray-800/60 rounded-lg p-3.5 text-center">
                                <p class="text-gray-400 text-[11px] mb-2.5">Your team has withdrawn from this player.</p>
                                <button @click="sealedReinstate()" x-show="sealed.state === 'collecting'"
                                        class="w-full py-2.5 rounded-lg font-bold text-xs bg-gray-800 border border-gray-700 text-gray-200 hover:bg-gray-700 transition">
                                    RE-ENTER
                                </button>
                            </div>
                        </template>

                        {{-- Bidding --}}
                        <template x-if="sealedCanBid">
                            <div>
                                {{-- The round's clock.
                                     The server has always refused a late submission — ClosedBidService::submit()
                                     checks closedBidRoundTimerState() before anything else — but the team could
                                     not SEE the deadline, so the first they knew of it was a rejection. The bar
                                     turns amber then red as it runs down, and everything below locks at zero. --}}
                                <template x-if="sealedSecondsLeft !== null">
                                    <div class="mb-2.5 rounded-lg border p-2.5"
                                         :class="sealedExpired
                                            ? 'bg-red-500/10 border-red-500/40'
                                            : (sealedSecondsLeft <= 10 ? 'bg-red-500/10 border-red-500/30' : 'bg-gray-900/60 border-gray-800/60')">
                                        <div class="flex items-center justify-between mb-1.5">
                                            <span class="text-[10px] uppercase tracking-wider"
                                                  :class="sealedExpired ? 'text-red-400' : 'text-gray-400'"
                                                  x-text="sealedExpired ? 'Time is up' : 'Time remaining'"></span>
                                            <span class="font-bold tabular-nums"
                                                  :class="sealedExpired ? 'text-red-400 text-sm'
                                                        : (sealedSecondsLeft <= 10 ? 'text-red-400 text-lg animate-pulse' : 'text-white text-lg')"
                                                  x-text="sealedClockText"></span>
                                        </div>
                                        <div class="h-1.5 rounded-full bg-gray-800 overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-500 ease-linear"
                                                 :class="sealedSecondsLeft <= 10 ? 'bg-red-500' : (sealedSecondsLeft <= 30 ? 'bg-amber-400' : 'bg-cyan-500')"
                                                 :style="`width: ${sealedTimerPct}%`"></div>
                                        </div>
                                    </div>
                                </template>

                                <div class="bg-gray-900/60 border border-gray-800/60 rounded-lg p-3 mb-2.5">
                                    {{-- A tie-break round tells the team the amount to beat and
                                         how many shared it. Never which teams, never a losing
                                         amount. --}}
                                    <template x-if="sealed.round_number > 1">
                                        <p class="text-amber-300 text-[11px] text-center mb-2">
                                            <span x-text="sealed.tied_count"></span> teams bid
                                            <span class="font-bold" x-text="formatCurrency(sealed.tie_amount)"></span>.
                                            Enter above that.
                                        </p>
                                    </template>

                                    <label class="text-gray-400 text-[10px] uppercase tracking-wider mb-1.5 block text-center">
                                        Bid Amount (M) &middot;
                                        steps of <span x-text="toM(sealed.step)"></span> &middot;
                                        min <span x-text="toM(sealed.floor)"></span> &middot;
                                        max <span :class="sealedCeiling < sealed.floor ? 'text-amber-400 font-bold' : ''"
                                                  x-text="toM(sealedCeiling)"></span>
                                    </label>

                                    {{-- Why the ceiling is what it is.
                                         The figures were only shown on the ACCEPT step, so by the time a
                                         manager was actually typing an amount all they saw was a bare "max",
                                         with no way to tell whether they were short of money or holding back
                                         for places they still have to fill. Both are named, and the one doing
                                         the limiting is highlighted. --}}
                                    <template x-if="sealed.ceilings">
                                        <p class="text-[10px] text-center mb-1.5 leading-relaxed"
                                           :class="sealedCeiling < sealed.floor ? 'text-amber-400' : 'text-gray-500'">
                                            You can bid up to
                                            <span class="font-bold text-emerald-400" x-text="formatCurrency(sealedCeiling)"></span>
                                            &middot;
                                            <span class="font-semibold"
                                                  x-text="(sealed.ceilings.slots_remaining ?? 0) + ' place' + ((sealed.ceilings.slots_remaining ?? 0) === 1 ? '' : 's') + ' still to fill'"></span>
                                            <br>
                                            <span x-text="formatCurrency(sealed.ceilings.remaining_budget)"></span> left,
                                            holding back
                                            <span x-text="formatCurrency(sealed.ceilings.reserve_amount)"></span>
                                            for them
                                            <span x-show="sealedCapBinds" class="text-amber-400">
                                                &middot; capped at
                                                <span x-text="sealed.ceilings.per_player_cap_pct ?? 70"></span>% per player
                                            </span>
                                        </p>
                                    </template>

                                    {{-- Hide the amount from the room.
                                         A sealed bid is only sealed from the other TEAMS; managers enter it on
                                         a laptop at a shared table, where the number is the largest thing on the
                                         screen and readable from the next chair. Masked by default, revealed
                                         deliberately, and re-masked on submit so the last bid does not sit on
                                         screen afterwards. The stepper still works while hidden. --}}
                                    <div class="flex items-center justify-center mb-1.5">
                                        <button type="button" @click="sealedAmountHidden = !sealedAmountHidden"
                                                class="inline-flex items-center gap-1.5 text-[10px] uppercase tracking-wider transition"
                                                :class="sealedAmountHidden ? 'text-cyan-400 hover:text-cyan-300' : 'text-gray-500 hover:text-gray-300'">
                                            <template x-if="sealedAmountHidden">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                                </svg>
                                            </template>
                                            <template x-if="!sealedAmountHidden">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </template>
                                            <span x-text="sealedAmountHidden ? 'Amount hidden — tap to show' : 'Amount visible — tap to hide'"></span>
                                        </button>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button @click="sealedStepDown()" :disabled="sealedRaw <= sealed.floor"
                                                class="w-9 h-9 rounded-md bg-red-500/15 border border-red-500/25 text-red-400 text-base font-bold flex items-center justify-center hover:bg-red-500/25 transition shrink-0 disabled:opacity-40">&minus;</button>
                                        <div class="flex-1">
                                            {{-- x-model, NOT a :value bound to a converted number.
                                                 The old binding recomputed the displayed value on
                                                 every keystroke, so typing "9" then "." erased the
                                                 decimal point and 9.1 could not be entered at all. --}}
                                            <div class="flex items-center bg-gray-800/80 border rounded-md"
                                                 :class="sealedStepViolation ? 'border-red-500/40' : 'border-gray-700/50 focus-within:border-cyan-500/60'">
                                                {{-- :type toggles number/password for the privacy mask.
                                                     A password field cannot carry step/min/max, so those are
                                                     bound conditionally; the real limits are enforced by
                                                     sealedCanSubmit and, finally, by the server. --}}
                                                <input :type="sealedAmountHidden ? 'password' : 'number'"
                                                       x-model="sealedInputM"
                                                       @keydown.enter.prevent="sealedCanSubmit && sealedSubmit()"
                                                       :step="sealedAmountHidden ? null : toM(sealed.step)"
                                                       :min="sealedAmountHidden ? null : toM(sealed.floor)"
                                                       :max="sealedAmountHidden ? null : toM(sealedCeiling)"
                                                       :disabled="sealedExpired"
                                                       autocomplete="off"
                                                       inputmode="decimal"
                                                       class="w-full px-2.5 py-2 bg-transparent text-white text-base text-center focus:outline-none font-bold disabled:opacity-50"
                                                       :placeholder="sealedAmountHidden ? '••••' : toM(sealed.floor)">
                                                <span class="pr-2.5 text-gray-500 font-semibold text-xs">M</span>
                                            </div>
                                        </div>
                                        <button @click="sealedStepUp()" :disabled="sealedRaw >= sealedCeiling"
                                                class="w-9 h-9 rounded-md bg-green-500/15 border border-green-500/25 text-green-400 text-base font-bold flex items-center justify-center hover:bg-green-500/25 transition shrink-0 disabled:opacity-40">+</button>
                                    </div>

                                    {{-- Both neighbours named: "invalid amount" is no use to
                                         somebody under a clock. --}}
                                    <p x-show="sealedStepViolation" class="text-red-400 text-[11px] mt-2 text-center">
                                        Bids must be in steps of <span x-text="toM(sealed.step)"></span>M.
                                        <span x-text="toM(sealedRaw)"></span>M is not allowed — try
                                        <span class="font-bold" x-text="toM(sealedNearestBelow)"></span>M or
                                        <span class="font-bold" x-text="toM(sealedNearestAbove)"></span>M.
                                    </p>

                                    <p x-show="sealedCeiling < sealed.floor" class="text-amber-400 text-[11px] mt-2 text-center">
                                        Your cap is below this round's minimum — you cannot bid on this player.
                                    </p>
                                </div>

                                <button @click="sealedSubmit()" :disabled="!sealedCanSubmit"
                                        class="w-full py-3 rounded-lg font-bold text-sm transition-all"
                                        :class="sealedCanSubmit
                                            ? 'bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white shadow-lg shadow-green-500/20'
                                            : 'bg-gray-800/60 border border-gray-700/40 text-gray-500 cursor-not-allowed'">
                                    <span x-text="isSubmitting ? 'Submitting…' : (sealed.my_entry?.amount ? 'CHANGE SEALED BID' : 'PLACE SEALED BID')"></span>
                                </button>

                                <div class="flex items-center justify-between mt-2 text-[10px]">
                                    <span class="text-gray-500">
                                        Round <span x-text="sealed.round_number"></span> of <span x-text="sealed.total_rounds"></span>
                                    </span>
                                    <button @click="sealedWithdraw()" class="text-gray-500 hover:text-red-400 transition">Withdraw</button>
                                </div>
                            </div>
                        </template>

                        {{-- Submitted, waiting for the round to close --}}
                        {{-- Once a bid is in, this replaces the entry box for the rest of the round —
                             it is no longer only a post-lock summary, because a submitted team can no
                             longer edit and has nothing else to look at. --}}
                        <template x-if="sealed.active && sealed.my_entry?.amount && (sealed.state !== 'collecting' || sealedEntryState === 'submitted')">
                            <div class="bg-gray-900/60 border border-emerald-500/25 rounded-lg p-3.5 text-center">
                                <div class="text-gray-400 text-[10px] uppercase tracking-wider mb-1">Your sealed bid</div>
                                {{-- Masked here too. This panel is on screen for the whole rest of the
                                     round, so leaving the figure on it would undo the mask on the input
                                     the moment the bid was placed. --}}
                                <div class="text-emerald-400 text-xl font-bold"
                                     x-text="sealedAmountHidden ? '••••••' : formatCurrency(sealed.my_entry.amount)"></div>
                                <button type="button" @click="sealedAmountHidden = !sealedAmountHidden"
                                        class="text-[10px] uppercase tracking-wider mt-1 transition"
                                        :class="sealedAmountHidden ? 'text-cyan-400 hover:text-cyan-300' : 'text-gray-500 hover:text-gray-300'"
                                        x-text="sealedAmountHidden ? 'Show' : 'Hide'"></button>
                                <p class="text-gray-500 text-[10px] mt-1.5"
                                   x-text="sealed.state === 'collecting'
                                        ? 'This is final — it cannot be changed. Waiting for the other teams.'
                                        : 'Bidding is closed. Waiting for the result.'"></p>
                            </div>
                        </template>
                    </div>

                    {{-- OFFLINE MODE — open bidding only.
                         Shown for the OPEN round, where the organizer really is taking the raises
                         from the room. A sealed round is entered here regardless of mode, so this
                         must not claim otherwise while the sealed box is on screen above it. --}}
                    <div x-show="auctionMode === 'offline' && bidType !== 'closed'" class="text-center">
                        <div class="bg-orange-500/10 border border-orange-500/25 rounded-lg p-3.5">
                            <div class="flex items-center justify-center gap-1.5 mb-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                                <span class="text-orange-400 text-[11px] font-bold uppercase tracking-wider">Offline Bidding</span>
                            </div>
                            <p class="text-gray-400 text-[11px]">The organizer is calling the open bidding in the room.</p>
                        </div>
                    </div>

                    {{-- Messages --}}
                    <div x-show="bidSuccess" x-transition class="mt-2.5 px-3 py-2 bg-green-500/10 border border-green-500/25 rounded-md text-center" x-cloak>
                        <p class="text-green-400 text-[11px] font-medium" x-text="bidSuccess"></p>
                    </div>
                    <p x-show="bidError" class="text-red-400 text-[11px] mt-2 text-center" x-text="bidError" x-cloak></p>
                </div>

                {{-- SOLD OVERLAY --}}
                <div x-show="state === 'sold'" x-transition x-cloak class="absolute inset-0 bg-gray-950/95 backdrop-blur-sm flex items-center justify-center z-30">
                    <div class="text-center px-6 max-w-sm">
                        <div class="w-24 h-24 mx-auto rounded-full overflow-hidden ring-4 ring-emerald-500/60 shadow-lg shadow-emerald-500/20 mb-3">
                            <template x-if="soldPlayerImage">
                                <img :src="soldPlayerImage" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!soldPlayerImage">
                                <div class="w-full h-full bg-gradient-to-br from-orange-500 to-orange-700 flex items-center justify-center text-xl font-bold text-white"
                                     x-text="soldPlayerName?.substring(0,2).toUpperCase() || 'P'"></div>
                            </template>
                        </div>
                        <div class="sold-stamp inline-block bg-emerald-500 text-white px-5 py-1.5 text-xl font-black tracking-wider rounded uppercase mb-2" style="transform: rotate(-12deg);">SOLD!</div>
                        <h2 class="text-xl font-bold leading-tight" x-text="soldPlayerName"></h2>
                        <div class="text-2xl font-black text-emerald-400 mt-1" x-text="formatCurrency(soldPrice)"></div>
                        <div class="flex items-center justify-center gap-2 mt-2">
                            <span class="text-gray-500 text-xs">Sold to</span>
                            <template x-if="soldTeamLogo">
                                <img :src="soldTeamLogo" class="w-6 h-6 rounded-full object-cover ring-1 ring-gray-600">
                            </template>
                            <span class="text-sm font-bold"
                                  :class="soldTeamName === '{{ $userTeam->name }}' ? 'text-green-400' : 'text-gray-300'"
                                  x-text="soldTeamName"></span>
                            <span x-show="soldTeamName === '{{ $userTeam->name }}'" class="px-1.5 py-px bg-green-500 text-white text-[9px] rounded font-bold uppercase">Your Team!</span>
                        </div>
                        <div x-show="soldTeamName === '{{ $userTeam->name }}'" class="mt-2 inline-block px-3 py-1 bg-green-500/15 border border-green-500/25 rounded-full">
                            <span class="text-green-400 font-semibold text-xs">Player added to your squad!</span>
                        </div>
                    </div>
                </div>

                {{-- UNSOLD OVERLAY --}}
                <div x-show="state === 'unsold'" x-transition x-cloak class="absolute inset-0 bg-gray-950/95 backdrop-blur-sm flex items-center justify-center z-30">
                    <div class="text-center px-6">
                        <div class="w-24 h-24 mx-auto rounded-full overflow-hidden ring-4 ring-red-500/50 shadow-lg shadow-red-500/20 mb-3">
                            <template x-if="unsoldPlayerImage">
                                <img :src="unsoldPlayerImage" class="w-full h-full object-cover grayscale">
                            </template>
                            <template x-if="!unsoldPlayerImage">
                                <div class="w-full h-full bg-gray-800 flex items-center justify-center">
                                    <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                            </template>
                        </div>
                        <div class="unsold-stamp inline-block bg-red-500 text-white px-5 py-1.5 text-xl font-black tracking-wider rounded uppercase mb-2" style="transform: rotate(-12deg);">Unsold</div>
                        <h2 class="text-xl font-bold" x-text="unsoldPlayerName"></h2>
                        <p class="text-gray-500 text-xs mt-1">No bids received</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════ SIDE PANEL ═══════════════════════ --}}
        <div x-show="!isFullscreen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-x-full opacity-0"
             x-transition:enter-end="translate-x-0 opacity-100"
             class="w-72 flex-shrink-0 bg-gray-900/95 border-l border-gray-800/50 flex-col hidden lg:flex">

            {{-- Budget Card --}}
            <div class="p-3 border-b border-gray-800/40">
                <div class="bg-gradient-to-br from-emerald-900/30 to-gray-900/50 border border-emerald-800/30 rounded-lg p-3 text-center">
                    <div class="text-[9px] text-gray-400 uppercase tracking-[0.12em] mb-0.5">Remaining Budget</div>
                    <div class="text-xl font-black leading-tight"
                         :class="teamBudget < teamPurse * 0.2 ? 'text-red-400' : 'text-emerald-400'"
                         x-text="formatCurrency(teamBudget)"></div>
                    <div class="w-full bg-gray-700/40 rounded-full h-1 mt-2">
                        <div class="h-1 rounded-full transition-all duration-500"
                             :class="teamBudget < teamPurse * 0.2 ? 'bg-red-500' : 'bg-emerald-500'"
                             :style="`width: ${Math.max(0, Math.min(100, (teamBudget / (teamAllocated || 1)) * 100))}%`"></div>
                    </div>

                    {{-- What that budget actually allows on THIS player.
                         The remaining budget on its own is a misleading number to bid against:
                         a team with 40M left and eight places still to fill cannot spend 40M,
                         and the page only told it so by refusing the bid. The ceiling, the
                         places still to fill and the money held back for them are stated
                         together, because the figure makes no sense without its reason. --}}
                    <div class="mt-2.5 pt-2.5 border-t border-emerald-800/30 text-left space-y-1">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-[9px] text-gray-400 uppercase tracking-[0.12em]">Max on one player</span>
                            <span class="text-sm font-bold text-cyan-400" x-text="formatCurrency(openMaxBid)"></span>
                        </div>
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-[9px] text-gray-500">Places still to fill</span>
                            <span class="text-[11px] font-semibold text-gray-300" x-text="slotsRemaining"></span>
                        </div>
                        <div class="flex items-baseline justify-between gap-2" x-show="reserveAmount > 0">
                            <span class="text-[9px] text-gray-500">Held back for them</span>
                            <span class="text-[11px] font-semibold text-amber-400" x-text="formatCurrency(reserveAmount)"></span>
                        </div>
                        {{-- Only when a cap is configured. Saying "capped at 100%" where no cap
                             exists invents a rule. --}}
                        <div class="flex items-baseline justify-between gap-2" x-show="openCapPct !== null && openCapPct !== undefined">
                            <span class="text-[9px] text-gray-500">
                                Per-player cap (<span x-text="openCapPct"></span>%)
                            </span>
                            <span class="text-[11px] font-semibold"
                                  :class="openCap <= maxBidAllowed ? 'text-amber-400' : 'text-gray-400'"
                                  x-text="formatCurrency(openCap)"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Your Squad --}}
            <div class="flex-1 flex flex-col min-h-0">
                <div class="px-3 pt-3 pb-1.5 flex items-center justify-between">
                    <h3 class="text-[9px] uppercase tracking-[0.12em] text-gray-500 font-semibold">Your Squad</h3>
                    <span class="text-[9px] px-1.5 py-px bg-emerald-500/15 text-emerald-400 rounded-full font-semibold" x-text="mySquad.length"></span>
                </div>
                <div class="flex-1 overflow-y-auto px-3 pb-3 space-y-0.5">
                    <template x-for="sp in mySquad" :key="sp.id">
                        <div class="flex items-center gap-2 px-2 py-1.5 rounded-md bg-green-500/8 border border-green-500/15">
                            <div class="w-6 h-6 rounded-full bg-gradient-to-br from-orange-500 to-orange-700 flex items-center justify-center text-white font-bold text-[8px] flex-shrink-0"
                                 x-text="(sp.player?.name || 'P').substring(0,2).toUpperCase()"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] font-medium text-white truncate leading-tight" x-text="sp.player?.name"></p>
                                <p class="text-[9px] text-gray-500 truncate leading-tight" x-text="sp.player?.player_type || ''"></p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-[10px] font-bold text-emerald-400 leading-tight" x-text="formatCurrency(sp.final_price)"></p>
                            </div>
                        </div>
                    </template>
                    <div x-show="mySquad.length === 0" class="text-center py-8">
                        <p class="text-gray-600 text-[11px]">No players in your squad yet</p>
                    </div>
                </div>
            </div>

            {{-- Admin preview links --}}
            @if(isset($isPreviewMode) && $isPreviewMode)
            <div class="p-3 border-t border-gray-800/40 space-y-1.5">
                <a href="{{ route('team.auction.bidding.show', $auction->id) }}" class="block w-full text-center py-1.5 rounded-md bg-white/5 text-white text-[11px] hover:bg-white/10 transition font-medium">
                    Switch Team
                </a>
                <a href="{{ route('admin.auction.organizer.panel', $auction->id) }}" class="block w-full text-center py-1.5 rounded-md bg-cyan-500/10 text-cyan-400 text-[11px] hover:bg-cyan-500/20 transition font-medium">
                    Organizer Panel
                </a>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function teamBiddingPanel() {
    return {
        auctionId: {{ $auction->id }},
        userTeam: @json($userTeam),
        // Chained poll handle, and the ordering token for pushed raises: socket frames
        // arrive unordered, so anything not newer than this is dropped.
        _pollTimer: null,
        _lastAppliedBidId: 0,
        player: { id: null, name: "", image_url: "", base_price: 0, current_price: 0, current_bid_team: null, role: "", batting_style: "", bowling_style: "", total_matches: null, total_runs: null, total_wickets: null },
        soldPlayers: @json($soldPlayers ?? []),
        state: "waiting",
        auctionStatus: "{{ $auction->status }}",
        bidType: "{{ $auction->bid_type ?? 'open' }}",
        teamBudget: {{ $remainingBudget ?? 0 }},
        // The configured total for this team, and what is left after its retentions.
        teamAllocated: {{ $maxBudget ?? 0 }},
        teamPurse: {{ $purse['auction_purse'] ?? ($maxBudget ?? 0) }},
        maxBidAllowed: {{ $maxBidAllowed ?? 0 }},
        /*
         * The team this page is being viewed AS, when an admin is previewing.
         *
         * The page has always honoured ?team_id=, but its polls did not send it — so every poll
         * behind a previewed page answered as "no team", and the sealed round in particular came
         * back with no entry at all. Carried on the requests that are team-specific.
         */
        previewTeamId: {{ ($isPreviewMode ?? false) && isset($userTeam) ? (int) $userTeam->id : 'null' }},
        /*
         * The open round's own ceiling, and why it is what it is.
         *
         * The page has always known maxBidAllowed — it greys the bid button out with
         * "BUDGET EXCEEDED" — but never showed the number, so a manager found the limit by
         * hitting it. These three name it: the reserve maximum, the optional per-player cap,
         * and the lower of the two. `openCapPct` is null when no cap is configured, and the
         * panel then says nothing about one rather than drawing a limit that does not exist.
         */
        openMaxBid: {{ $maxBidAllowed ?? 0 }},
        openCap: 0,
        openCapPct: null,
        slotsRemaining: {{ $purse['slots_remaining'] ?? 0 }},
        reserveAmount: {{ $purse['reserve'] ?? 0 }},

        /* ── Sealed round ──
           `sealed` is the server's view of the round for THIS team. It never contains a
           rival's amount, so there is nothing here to hide client-side. */
        sealed: { active: false },
        // The literal text typed into the amount box. Held as a string and never written
        // back while focused — see the input's comment.
        sealedInputM: '',
        /*
         * The sealed amount is masked by default.
         *
         * A sealed bid is sealed from the other TEAMS, not from the person typing it — and
         * managers type it on a laptop at a shared table where the figure is the largest
         * thing on screen. Defaulting to hidden is the only setting that protects the first
         * bid of the round, before anyone has thought to press anything.
         */
        sealedAmountHidden: true,
        /*
         * Seconds left in the round, ticked locally between polls.
         *
         * Seeded from the server on every poll (`sealed.timer.remaining`) and only counted
         * down in between, so a client whose clock is wrong or whose tab was throttled is
         * corrected within one poll instead of drifting. null means no round is running.
         */
        sealedSecondsLeft: null,
        _sealedTicker: null,
        bidError: "",
        bidSuccess: "",
        isSubmitting: false,
        lastPlayerId: null,
        myBidAmount: {{ isset($myBid) && $myBid ? $myBid->amount : 0 }},
        auctionMode: "{{ $auction->open_bid_mode ?? 'online' }}",
        bidRules: @json($auction->bid_rules ?? []),
        isFullscreen: false,
        bidJustChanged: false,
        _bidFlashTimeout: null,

        soldPlayerName: '', soldPlayerImage: null, soldPrice: 0,
        soldTeamName: '', soldTeamLogo: null,
        unsoldPlayerName: '', unsoldPlayerImage: null,

        BID_TIMER_DURATION: {{ $auction->bid_timer_seconds ?? 30 }},
        BID_TIMER_RESET_TO: {{ $auction->bid_timer_reset_seconds ?? 15 }},
        timerSeconds: 0, timerWidth: 100, timerInterval: null,
        // What amounts are called, from the auction's settings.
        amountUnit: @json($auction->amountUnitConfig()),
        // Closing calls, thresholds supplied by the server.
        finalCall: null,
        finalCallStages: @json($auction->finalCallStages()),
        timerExpired: false, lastKnownPrice: 0, lastServerUpdatedAt: 0,

        init() {
            if (this.auctionStatus === "completed") {
                this.state = "completed";
            } else {
                const initialPlayer = @json($currentPlayer);
                if (initialPlayer) {
                    this.setPlayerOnBid(initialPlayer);
                    this.lastPlayerId = initialPlayer.id;
                    this.lastKnownPrice = Number(initialPlayer.current_price) || Number(initialPlayer.base_price) || 0;
                }
            }
            this.startPolling();
            this.subscribeToRaises();
            document.addEventListener('fullscreenchange', () => {
                this.isFullscreen = !!document.fullscreenElement;
            });
        },

        toggleFullscreen() {
            const el = document.getElementById('biddingWrapper');
            if (!document.fullscreenElement) {
                (el || document.documentElement).requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen().catch(() => {});
            }
        },

        /**
         * Chained, not setInterval.
         *
         * This screen fired four requests in parallel every 2 s regardless of whether the
         * previous four had finished — the one live screen that never got the chained-poll
         * treatment the organizer panels have. With SESSION_DRIVER=file each request takes
         * an exclusive lock on the session file, so those four serialise anyway and a slow
         * tick simply stacked more work behind itself. Waiting for the round trip before
         * scheduling the next one keeps at most one tick in flight.
         *
         * The interval is unchanged. This is the fallback path now — pushed raises arrive
         * through subscribeToRaises() in milliseconds — so it only has to be steady, not
         * fast.
         */
        startPolling() {
            const tick = async () => {
                try {
                    await Promise.all([
                        this.fetchCurrentPlayer(),
                        this.fetchSoldPlayers(),
                        this.fetchPurse(),
                        this.fetchSealed(),
                    ]);
                } catch (e) {
                    // A failed tick must not end the loop — that is what would leave the
                    // screen frozen for the rest of the auction.
                } finally {
                    this._pollTimer = setTimeout(tick, 2000);
                }
            };

            // First pass immediately, same as before.
            tick();
        },

        /**
         * Take a raise straight off the wire.
         *
         * This is the screen the delay actually mattered on: a manager was deciding against
         * a price up to ~3 s old (2 s poll + 1 s feed cache), with the clock running. The
         * poll above still runs and remains the source of truth.
         */
        subscribeToRaises() {
            if (!window.auctionChannel) {
                window.addEventListener('load', () => this.subscribeToRaises(), { once: true });
                return;
            }

            const channel = window.auctionChannel(this.auctionId);
            if (!channel) return;

            channel.listen('.bid.raised', (e) => this.applyRaise(e));
        },

        /**
         * Socket frames are unordered and may repeat, so `bid_id` decides: anything not
         * newer than the last applied frame is dropped. Otherwise a late frame can show a
         * price below what the bidding has already reached — and this is the screen where
         * somebody is about to bet money on that number.
         */
        applyRaise(e) {
            if (!e || !this.player) return;

            // Only the player this screen currently shows. `lastPlayerId` is what the poll
            // path tracks, so the two agree on who is up.
            if (Number(e.auction_player_id) !== Number(this.lastPlayerId)) return;

            const bidId = Number(e.bid_id) || 0;
            if (bidId <= (this._lastAppliedBidId || 0)) return;
            this._lastAppliedBidId = bidId;

            const price = Number(e.current_price);
            if (!isFinite(price)) return;

            if (price !== this.player.current_price) this._flashBid();

            this.player.current_price = price;
            this.player.current_bid_team = e.current_bid_team_id
                ? { id: e.current_bid_team_id, name: e.team_name }
                : null;

            /*
             * The frame carries the poll's own timer field names, so the existing re-seed
             * handles it. Passing a partial object here would be worse than doing nothing:
             * syncTimerFromServer() treats a missing `timer_seconds_remaining` as "no clock"
             * and would zero the countdown on every raise.
             */
            this.syncTimerFromServer(e);
        },

        /**
         * Apply a purse payload from the server.
         *
         * The purse used to be seeded once at page load and never refreshed, so a team
         * watched a stale figure for the whole auction — and the endpoint that returns
         * it had no caller at all.
         */
        /* ── Sealed round: derived state ── */

        get sealedEntryState() {
            if (!this.sealed.active || !this.sealed.my_entry) return 'none';
            return this.sealed.my_entry.withdrawn ? 'withdrawn' : this.sealed.my_entry.state;
        },

        /** The lower of the per-player cap and the squad-reserve maximum. */
        get sealedCeiling() {
            return Number(this.sealed.ceilings?.binding ?? 0);
        },

        /** Which rule is actually holding this team back — worth saying out loud. */
        get sealedCapBinds() {
            const cap = Number(this.sealed.ceilings?.per_player_cap ?? Infinity);
            const reserve = Number(this.sealed.ceilings?.reserve_max ?? Infinity);
            return cap <= reserve;
        },

        /** Typed millions → raw units. One-way, so it never fights the input. */
        get sealedRaw() {
            return this.fromM(this.sealedInputM) || 0;
        },

        /**
         * Integer cents, because the money grain is two decimal places and a float
         * modulo would refuse legal amounts — 0.3 % 0.1 is not 0 in binary.
         */
        get sealedStepViolation() {
            const step = Math.round(Number(this.sealed.step || 0) * 100);
            if (!(step > 0)) return false;
            const cents = Math.round(this.sealedRaw * 100);
            return cents > 0 && cents % step !== 0;
        },

        get sealedNearestBelow() {
            const step = Number(this.sealed.step || 0);
            return step > 0 ? Math.floor(this.sealedRaw / step) * step : this.sealedRaw;
        },

        get sealedNearestAbove() {
            return this.sealedNearestBelow + Number(this.sealed.step || 0);
        },

        /** Is the team in a position to type an amount at all? */
        /** `?team_id=` when previewing, so a poll answers for the team on screen. */
        teamQuery() {
            return this.previewTeamId ? `?team_id=${this.previewTeamId}` : '';
        },

        get sealedCanBid() {
            if (!this.sealed.active || this.sealed.state !== 'collecting') return false;
            // Not one of the teams this round was opened to. Without this, a round that
            // does not require acceptance offered the bid box to every team.
            if (this.sealed.invited === false) return false;
            if (this.sealedEntryState === 'withdrawn' || this.sealedEntryState === 'declined') return false;

            /*
             * Already in. A sealed bid is one committed decision, so the box closes.
             *
             * The button used to read CHANGE SEALED BID and the server accepted the change — a
             * manager could sit on the clock and revise, which is exactly the behaviour a sealed
             * round exists to prevent. The server now refuses it; this stops the screen offering
             * an edit that would only come back as an error.
             */
            if (this.sealedEntryState === 'submitted' && this.sealed.my_entry?.amount) return false;

            if (this.sealed.requires_acceptance) {
                return ['accepted', 'must_rebid', 'may_opt_in'].includes(this.sealedEntryState);
            }
            return true;
        },

        get sealedExpired() {
            return this.sealedSecondsLeft !== null && this.sealedSecondsLeft <= 0;
        },

        get sealedClockText() {
            const s = Math.max(0, Number(this.sealedSecondsLeft || 0));
            return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
        },

        /** Width of the countdown bar, as a share of the round's own limit. */
        get sealedTimerPct() {
            const limit = Number(this.sealed.timer?.limit || 0);
            if (!(limit > 0) || this.sealedSecondsLeft === null) return 0;
            return Math.max(0, Math.min(100, (this.sealedSecondsLeft / limit) * 100));
        },

        get sealedCanSubmit() {
            return this.sealedCanBid
                && !this.isSubmitting
                && !this.sealedStepViolation
                // The server refuses a late submission outright; this stops the team wasting
                // the last seconds of a round on a request that cannot be accepted.
                && !this.sealedExpired
                && this.sealedRaw >= Number(this.sealed.floor || 0)
                && this.sealedRaw <= this.sealedCeiling;
        },

        /**
         * Take the round's clock from the server and keep it ticking between polls.
         *
         * `remaining` is null until the organizer starts the timer, which is not the same as
         * zero — the round is open and simply not counting yet, so the clock is hidden rather
         * than shown expired.
         */
        syncSealedTimer() {
            const timer = this.sealed?.active ? this.sealed.timer : null;

            if (!timer || timer.remaining === null || timer.remaining === undefined) {
                this.sealedSecondsLeft = null;
                return;
            }

            this.sealedSecondsLeft = Math.max(0, Number(timer.remaining));

            if (this._sealedTicker) return;

            this._sealedTicker = setInterval(() => {
                if (this.sealedSecondsLeft === null) return;
                if (this.sealedSecondsLeft > 0) this.sealedSecondsLeft--;
            }, 1000);
        },

        /* ── Sealed round: actions ── */

        /** Write a canonical figure back into the box. Only buttons call this. */
        setSealed(raw) {
            this.sealedInputM = String(this.toM(raw));
        },

        sealedStepUp() {
            const step = Number(this.sealed.step || 0);
            if (!(step > 0)) return;
            // Snap onto the grid as it steps, so pressing + also rescues an amount that
            // was typed off it.
            const next = this.sealedRaw > 0
                ? (Math.floor(this.sealedRaw / step) + 1) * step
                : Number(this.sealed.floor || 0);
            this.setSealed(Math.min(next, this.sealedCeiling));
        },

        sealedStepDown() {
            const step = Number(this.sealed.step || 0);
            if (!(step > 0)) return;
            const next = (Math.ceil(this.sealedRaw / step) - 1) * step;
            this.setSealed(Math.max(next, Number(this.sealed.floor || 0)));
        },

        applySealed(d) {
            if (!d) return;
            const previousRound = this.sealed.round_id;
            this.sealed = d;

            // A new round means a new floor; clear a stale typed amount rather than
            // leaving a figure that is now below the minimum.
            if (previousRound && d.round_id && previousRound !== d.round_id) {
                this.sealedInputM = '';
                // …and re-mask it. A fresh round is a fresh secret; leaving the box revealed
                // because it was revealed for the last player defeats the point of the mask.
                this.sealedAmountHidden = true;
            }

            // Re-seed the countdown from the server on every poll, so a client clock that is
            // wrong or a tab that was throttled is corrected rather than left to drift.
            this.syncSealedTimer();
        },

        async sealedPost(path, body = {}) {
            this.isSubmitting = true;
            this.bidError = '';
            this.bidSuccess = '';

            /*
             * A hung request must not lock the button.
             *
             * `finally` already resets isSubmitting, but only once the fetch settles — and a
             * request that never comes back never settles. During a live round the server was
             * saturated and taking 10-20s, so the button sat on "Submitting…" indefinitely
             * with no way to retry and no idea whether the bid had landed.
             *
             * Fifteen seconds, then abort and say so. The team can retry; the round's own
             * clock is the thing that decides whether they are still in time.
             */
            const abort = new AbortController();
            const timeout = setTimeout(() => abort.abort(), 15000);

            try {
                const res = await fetch(`/admin/team/auction/${this.auctionId}/api/closed-bid/${path}`, {
                    method: 'POST',
                    signal: abort.signal,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'That is not possible right now.');
                this.applySealed(data.sealed);
                this.applyPurse(data);
                if (data.message) this.bidSuccess = data.message;
            } catch (e) {
                this.bidError = e.name === 'AbortError'
                    ? 'The server did not answer in time. Your bid may not have been placed — check the board and try again.'
                    : e.message;
            } finally {
                clearTimeout(timeout);
                this.isSubmitting = false;
            }
        },

        sealedAccept() { return this.sealedPost('accept'); },
        sealedDecline() { return this.sealedPost('decline'); },
        sealedWithdraw() { return this.sealedPost('withdraw'); },
        sealedReinstate() { return this.sealedPost('reinstate'); },

        async sealedSubmit() {
            if (!this.sealedCanSubmit) return;
            await this.sealedPost('submit', { amount: this.sealedRaw });
            // Re-mask once it is in. Otherwise the round's most sensitive number sits on a
            // shared table for the rest of the round, which is exactly what the mask is for.
            this.sealedAmountHidden = true;
        },

        applyPurse(d) {
            if (!d) return;
            if (d.remaining_budget !== undefined) this.teamBudget = Number(d.remaining_budget);
            if (d.allocated !== undefined) this.teamAllocated = Number(d.allocated);
            if (d.auction_purse !== undefined) this.teamPurse = Number(d.auction_purse);
            if (d.max_bid_allowed !== undefined) this.maxBidAllowed = Number(d.max_bid_allowed);
            if (d.open_max_bid !== undefined) this.openMaxBid = Number(d.open_max_bid);
            if (d.open_per_player_cap !== undefined) this.openCap = Number(d.open_per_player_cap);
            if (d.open_per_player_cap_pct !== undefined) this.openCapPct = d.open_per_player_cap_pct;
            if (d.slots_remaining !== undefined) this.slotsRemaining = Number(d.slots_remaining);
            if (d.reserve_amount !== undefined) this.reserveAmount = Number(d.reserve_amount);
        },

        async fetchPurse() {
            try {
                const res = await fetch("/admin/team/auction/" + this.auctionId + "/api/purse" + this.teamQuery(), {
                    headers: { "Accept": "application/json" },
                });
                if (!res.ok) return;
                this.applyPurse(await res.json());
            } catch (e) { /* a dropped poll is not worth surfacing */ }
        },

        /**
         * Sealed state comes from an AUTHENTICATED endpoint, not the public feed the
         * rest of this page polls — the public feed deliberately carries no amounts at
         * all, not even this team's own.
         */
        async fetchSealed() {
            if (this.bidType !== 'closed') {
                if (this.sealed.active) {
                    this.sealed = { active: false };
                    this.syncSealedTimer();
                }
                return;
            }
            try {
                const res = await fetch(`/admin/team/auction/${this.auctionId}/api/closed-bid/state${this.teamQuery()}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                this.applySealed(data.sealed);
            } catch (e) { /* a dropped poll is not worth surfacing */ }
        },

        async fetchCurrentPlayer() {
            try {
                const res = await fetch("/auction/" + this.auctionId + "/active-player");
                const data = await res.json();
                if (data.auction_status) this.auctionStatus = data.auction_status;
                if (data.open_bid_mode) this.auctionMode = data.open_bid_mode;
                if (data.bid_type) this.bidType = data.bid_type;
                if (data.bid_rules) this.bidRules = data.bid_rules;

                if (data.auction_status === "completed") {
                    this.state = "completed";
                    this.resetPlayer();
                    return;
                }
                if (data.auctionPlayer) {
                    const isNewPlayer = data.auctionPlayer.id !== this.lastPlayerId;
                    if (isNewPlayer && (this.state === 'sold' || this.state === 'unsold')) {
                        this.state = 'waiting';
                    }
                    if (isNewPlayer || this.state === "waiting") {
                        this.lastPlayerId = data.auctionPlayer.id;
                        this.setPlayerOnBid(data.auctionPlayer);
                        this.myBidAmount = 0;
                        this.sealedInputM = "";
                        this.bidSuccess = "";
                        this.bidError = "";
                    }
                    const newPrice = Number(data.auctionPlayer.current_price) || this.player.current_price;
                    if (newPrice !== this.player.current_price) this._flashBid();
                    this.player.current_price = newPrice;
                    if (data.auctionPlayer.current_bid_team !== undefined) {
                        this.player.current_bid_team = data.auctionPlayer.current_bid_team;
                    }
                    this.syncTimerFromServer(data);
                } else if (this.state === "bidding" && this.lastPlayerId) {
                    const soldEntry = data.last_sold_player || null;
                    if (soldEntry && soldEntry.id === this.lastPlayerId) {
                        this.soldPlayerName = this.player.name;
                        this.soldPlayerImage = this.player.image_url?.includes('ui-avatars') ? null : this.player.image_url;
                        this.soldPrice = soldEntry.final_price || soldEntry.current_price || this.player.current_price;
                        this.soldTeamName = soldEntry.sold_to_team?.name || 'Unknown Team';
                        this.soldTeamLogo = soldEntry.sold_to_team?.logo_path || null; // already a full URL
                        this.state = "sold";
                    } else {
                        this.unsoldPlayerName = this.player.name;
                        this.unsoldPlayerImage = this.player.image_url?.includes('ui-avatars') ? null : this.player.image_url;
                        this.state = "unsold";
                    }
                    this.resetPlayer();
                    this.lastPlayerId = null;
                    this.stopTimer();
                    this.timerSeconds = 0;
                    this.timerWidth = 0;
                }
            } catch (e) { console.error("[BiddingPanel] Error:", e); }
        },

        async fetchSoldPlayers() {
            try {
                const res = await fetch("/auction/" + this.auctionId + "/sold-players");
                const data = await res.json();
                if (data.soldPlayers) this.soldPlayers = data.soldPlayers;
            } catch (e) {}
        },

        setPlayerOnBid(ap) {
            const p = ap.player;
            if (!p) return;
            let img = "https://ui-avatars.com/api/?name=" + encodeURIComponent(p.name || "P") + "&size=200&background=random";
            if (p.image_path) img = "/storage/" + p.image_path;
            const pt = p.player_type || p.playerType;
            const bp = p.batting_profile || p.battingProfile;
            const bw = p.bowling_profile || p.bowlingProfile;
            this.player = {
                id: ap.id,
                name: p.name || "Unknown",
                image_url: img,
                base_price: Number(ap.base_price) || 0,
                current_price: Number(ap.current_price) || Number(ap.base_price) || 0,
                current_bid_team: ap.current_bid_team || null,
                role: (typeof pt === "object" ? (pt.name || pt.type) : pt) || "Player",
                batting_style: (typeof bp === "object" ? (bp.style || bp.name) : bp) || "N/A",
                bowling_style: (typeof bw === "object" ? (bw.style || bw.name) : bw) || "N/A",
                total_matches: p.total_matches ?? null,
                total_runs: p.total_runs ?? null,
                total_wickets: p.total_wickets ?? null,
            };
            this.state = "bidding";
            this.timerExpired = false;
        },

        resetPlayer() {
            this.player = { id: null, name: "", image_url: "", base_price: 0, current_price: 0, current_bid_team: null, role: "", batting_style: "", bowling_style: "", total_matches: null, total_runs: null, total_wickets: null };
            this.myBidAmount = 0;
            this.sealedInputM = "";
            this.bidSuccess = "";
        },

        /**
         * What the next raise will cost.
         *
         * This MUST agree with BidIncrementService::incrementFor() on the server, because
         * the server is what actually charges the team. It did not: both used first-match,
         * and bands are written 1-2, 2-3, 3-5 so they share endpoints — at exactly 2M this
         * showed "your bid will be 2.1M" while the server placed 2.2M. A team was being told
         * one price and charged another.
         *
         * Same rule as the server now: among the bands containing the price, take the one
         * with the greatest `from`, so the higher band wins a shared boundary. For a price in
         * a gap, take the NEAREST band above rather than the first one declared above.
         */
        get nextBidAmount() {
            const current = this.player.current_price || this.player.base_price || 0;
            const rules = this.bidRules || [];

            let best = null;
            for (const r of rules) {
                const from = Number(r.from) || 0;
                const to = Number(r.to) || Infinity;
                const inc = Number(r.increment) || 0;
                if (inc <= 0 || current < from || current > to) continue;
                if (best === null || from > best.from) best = { from, inc };
            }

            if (best === null) {
                for (const r of rules) {
                    const from = Number(r.from) || 0;
                    const inc = Number(r.increment) || 0;
                    if (inc <= 0 || current >= from) continue;
                    if (best === null || from < best.from) best = { from, inc };
                }
            }

            return best ? current + best.inc : 0;
        },

        get canRaiseHand() {
            if (this.auctionMode === "offline") return false;
            if (this.state !== "bidding" || this.isSubmitting) return false;
            if (this.timerExpired) return false;
            if (this.isMyTeamHighest) return false;
            if (this.nextBidAmount <= 0) return false;
            if (this.nextBidAmount > this.maxBidAllowed) return false;
            return true;
        },




        get isMyTeamHighest() {
            return this.player.current_bid_team && this.player.current_bid_team.id === this.userTeam.id;
        },

        get mySquad() {
            return this.soldPlayers.filter(sp => sp.sold_to_team?.id === this.userTeam.id);
        },

        async raiseHand() {
            if (!this.canRaiseHand || !this.player.id) return;
            this.isSubmitting = true;
            this.bidError = "";
            this.bidSuccess = "";
            try {
                const res = await fetch("/admin/team/auction/" + this.auctionId + "/api/place-bid", {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content, "Accept": "application/json" },
                    body: JSON.stringify({ auction_player_id: this.player.id })
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || "Failed to place bid");
                this.myBidAmount = data.new_price || this.nextBidAmount;
                this.player.current_price = data.new_price || this.nextBidAmount;
                this.player.current_bid_team = { id: this.userTeam.id, name: this.userTeam.name };
                this.bidSuccess = "Bid placed! " + this.formatCurrency(this.myBidAmount);
                this.applyPurse(data);
                this._flashBid();
            } catch (e) { this.bidError = e.message; }
            finally { this.isSubmitting = false; }
        },

        handleKeydown(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
            if (e.key.toUpperCase() === 'F' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                this.toggleFullscreen();
                return;
            }
            if (e.key === ' ' || e.key === 'Enter') {
                e.preventDefault();
                if (this.bidType === 'open' && this.auctionMode !== 'offline') {
                    this.raiseHand();
                }
                return;
            }
        },

        /**
         * Seed the countdown from the server's authoritative clock.
         *
         * This used to infer elapsed time from `player_updated_at`, which changes on any
         * write to the row — so an unrelated update silently restarted the countdown.
         * The server now reports the remaining seconds directly, computed from
         * `timer_started_at`, so this page counts down in step with the organizer panel
         * and the big screen.
         */
        syncTimerFromServer(data) {
            if (data.amount_unit) this.amountUnit = data.amount_unit;
            if (Array.isArray(data.final_call_stages)) {
                this.finalCallStages = data.final_call_stages;
            }

            const enabled = data.timer_enabled !== false;
            const remaining = data.timer_seconds_remaining;

            if (!enabled || remaining === null || remaining === undefined) {
                // No clock running (e.g. offline mode with the timer switched off).
                this.stopTimer();
                this.timerSeconds = 0;
                this.timerWidth = 100;
                this.timerExpired = false;
                this.finalCall = null;
                return;
            }

            const limit = data.bid_timer_seconds || this.BID_TIMER_DURATION;

            if (remaining <= 0) {
                this.stopTimer();
                this.timerSeconds = 0;
                this.timerWidth = 0;
                this.timerExpired = true;
                this.refreshFinalCall();
                return;
            }

            // Re-seed whenever the server disagrees with the local tick by more than a
            // second — covers a restarted clock and a backgrounded tab alike.
            if (!this.timerInterval || Math.abs(remaining - this.timerSeconds) > 1) {
                this.startTimer(remaining, limit);
            }
        },

        startTimer(remaining, maxDuration) {
            this.stopTimer();
            this.timerSeconds = Math.ceil(remaining);
            const maxSeconds = maxDuration || remaining;
            this.timerWidth = Math.max(0, (this.timerSeconds / maxSeconds) * 100);
            this.timerExpired = false;
            this.refreshFinalCall();
            this.timerInterval = setInterval(() => {
                this.timerSeconds--;
                this.timerWidth = Math.max(0, (this.timerSeconds / maxSeconds) * 100);
                this.refreshFinalCall();
                if (this.timerSeconds <= 0) { this.stopTimer(); this.timerExpired = true; }
            }, 1000);
        },

        stopTimer() {
            if (this.timerInterval) { clearInterval(this.timerInterval); this.timerInterval = null; }
        },

        /** Closing call for the ticking countdown, from the server's thresholds. */
        refreshFinalCall() {
            this.finalCall = window.auctionFinalCallFor
                ? window.auctionFinalCallFor(this.timerSeconds, this.finalCallStages)
                : null;
        },

        _flashBid() {
            this.bidJustChanged = false;
            if (this._bidFlashTimeout) clearTimeout(this._bidFlashTimeout);
            setTimeout(() => {
                this.bidJustChanged = true;
                this._bidFlashTimeout = setTimeout(() => { this.bidJustChanged = false; }, 400);
            }, 10);
        },

        /** Money entry in millions, shared with every other screen. */
        toM(raw) { return window.auctionToM ? window.auctionToM(raw) : raw; },
        fromM(value) { return window.auctionFromM ? window.auctionFromM(value) : value; },

        /** Shared K/M/B formatter with this auction's unit. */
        formatCurrency(amt) {
            return window.auctionAmount
                ? window.auctionAmount(amt, this.amountUnit)
                : String(Number(amt) || 0);
        }
    };
}
</script>

{{-- Push layer, inside the scripts push so the layout actually renders it. Additive:
     the chained poll above stays the source of truth, so a socket that never connects
     leaves this screen behaving exactly as before. --}}
@include('backend.pages.auction.partials.echo-init')
@endpush
