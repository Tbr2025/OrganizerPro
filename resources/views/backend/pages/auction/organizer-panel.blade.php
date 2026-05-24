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
             'logo_path' => $t->logo_path,
             'players_bought' => $t->players_bought ?? 0,
             'total_spent' => $t->total_spent ?? 0,
             'remaining_budget' => $t->remaining_budget ?? $auction->max_budget_per_team,
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
                <label class="block text-sm font-medium text-gray-300 mb-2">Sale Amount (in Lakhs)</label>
                <div class="flex items-center bg-gray-900 border border-gray-600 rounded-xl focus-within:border-blue-500">
                    <input type="number"
                           :value="sellModalData.amount ? (sellModalData.amount / 100000) : ''"
                           @input="sellModalData.amount = Number($event.target.value) * 100000"
                           class="w-full px-4 py-3 bg-transparent text-white focus:outline-none text-right"
                           placeholder="0" step="0.5" min="0">
                    <span class="pr-4 text-gray-400 font-medium">L</span>
                </div>
            </div>
            <div x-show="sellModalData.team_id && sellModalData.amount" class="bg-gray-900/50 rounded-xl p-4 mb-6 text-center">
                <p class="text-gray-300">Sell to <span class="font-bold text-green-400" x-text="teams.find(t => t.id == sellModalData.team_id)?.name"></span></p>
                <p class="text-gray-300">for <span class="font-bold text-yellow-400 text-2xl" x-text="formatCurrency(sellModalData.amount)"></span></p>
            </div>
            <div class="flex gap-4">
                <button @click="showSellModal = false"
                        class="flex-1 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-xl font-medium transition">Cancel</button>
                <button @click="executeSellToTeam()"
                        :disabled="!sellModalData.team_id || !sellModalData.amount"
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
                                    <template x-if="teams.find(t => t.name === winningTeamName)?.logo_path">
                                        <img :src="'/storage/' + teams.find(t => t.name === winningTeamName).logo_path" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="!teams.find(t => t.name === winningTeamName)?.logo_path">
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
                                    <template x-if="getTeamById(tid)?.logo_path">
                                        <img :src="`/storage/${getTeamById(tid).logo_path}`" class="w-6 h-6 rounded-full object-cover">
                                    </template>
                                    <template x-if="!getTeamById(tid)?.logo_path">
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
                                <template x-if="team.logo_path">
                                    <img :src="`/storage/${team.logo_path}`" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                                </template>
                                <template x-if="!team.logo_path">
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
                                    <template x-if="getTeamById(tid)?.logo_path">
                                        <img :src="`/storage/${getTeamById(tid).logo_path}`" class="w-10 h-10 rounded-full object-cover">
                                    </template>
                                    <template x-if="!getTeamById(tid)?.logo_path">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold text-sm" x-text="getTeamById(tid)?.short_name || '?'"></div>
                                    </template>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-white truncate" x-text="getTeamById(tid)?.name"></p>
                                        <p class="text-xs text-gray-400">Budget: <span x-text="formatCurrency(getTeamById(tid)?.remaining_budget)"></span></p>
                                    </div>
                                    <div class="w-44">
                                        <div class="flex items-center bg-gray-700 border border-gray-600 rounded-lg focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500">
                                            <input type="number"
                                                   :value="offlineTeamBids[tid] / 100000"
                                                   @input="offlineTeamBids[tid] = Number($event.target.value) * 100000"
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
                        <template x-if="getTeamById(offlineHighestBidder)?.logo_path">
                            <img :src="`/storage/${getTeamById(offlineHighestBidder).logo_path}`" class="w-20 h-20 mx-auto rounded-full object-cover mb-3 border-4 border-green-500/50">
                        </template>
                        <template x-if="!getTeamById(offlineHighestBidder)?.logo_path">
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
                                    <template x-if="getTeamById(Number(entry[0]))?.logo_path">
                                        <img :src="`/storage/${getTeamById(Number(entry[0])).logo_path}`" class="w-10 h-10 rounded-full object-cover">
                                    </template>
                                    <template x-if="!getTeamById(Number(entry[0]))?.logo_path">
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
                        <template x-if="lastSoldPlayer?.winning_team?.logo_path">
                            <img :src="'/storage/' + lastSoldPlayer.winning_team.logo_path" class="w-12 h-12 rounded-full object-cover border-2 border-gray-600">
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
            <div x-show="displayState === 'bidding' && openBidMode !== 'offline'" class="h-1 bg-gray-800">
                <div class="h-full transition-all duration-1000 ease-linear"
                     :class="biddingTimerSeconds <= 5 ? 'bg-red-500' : 'bg-blue-500'"
                     :style="`width: ${timerWidth}%`"></div>
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

                <div class="w-px h-8 bg-gray-700"></div>

                {{-- 4. Team Buttons --}}
                <div class="flex-1 flex items-center justify-center gap-1.5 overflow-x-auto px-1 min-w-0">
                    <template x-for="(team, idx) in teams" :key="team.id">
                        <button @click="bidForTeam(team.id)"
                                :disabled="!currentPlayer || displayState !== 'bidding' || openBidMode === 'offline'"
                                :class="{
                                    'ring-2 ring-emerald-400 border-emerald-400 team-pulse': winningTeamName === team.name,
                                    'border-gray-600 hover:border-gray-400': winningTeamName !== team.name,
                                    'opacity-40 cursor-not-allowed': !currentPlayer || displayState !== 'bidding' || openBidMode === 'offline'
                                }"
                                class="relative w-10 h-10 rounded-full border-2 flex-shrink-0 flex items-center justify-center overflow-hidden transition-all group bg-gray-800"
                                :title="team.name + ' (' + formatCurrency(team.remaining_budget) + ' left)'">
                            <template x-if="team.logo_path">
                                <img :src="'/storage/' + team.logo_path" class="w-full h-full object-cover rounded-full">
                            </template>
                            <template x-if="!team.logo_path">
                                <span class="text-[10px] font-bold" x-text="team.name.substring(0, 2).toUpperCase()"></span>
                            </template>
                            <span class="absolute -top-1 -right-1 w-4 h-4 bg-gray-700 rounded-full text-[10px] font-mono flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                                  x-text="idx < 9 ? String(idx + 1) : (idx === 9 ? '0' : '')"></span>
                        </button>
                    </template>
                </div>

                <div class="w-px h-8 bg-gray-700"></div>

                {{-- 5. Action Buttons --}}
                <button @click="sellPlayer()"
                        :disabled="!currentPlayer || displayState !== 'bidding'"
                        class="px-3 py-1.5 text-white text-sm font-bold rounded transition-colors whitespace-nowrap"
                        :class="(currentPlayer && displayState === 'bidding') ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-gray-700 cursor-not-allowed opacity-50'">SELL</button>
                <button @click="passPlayer()"
                        :disabled="!currentPlayer || displayState !== 'bidding'"
                        class="px-3 py-1.5 text-white text-sm font-bold rounded transition-colors whitespace-nowrap"
                        :class="(currentPlayer && displayState === 'bidding') ? 'bg-red-600 hover:bg-red-500' : 'bg-gray-700 cursor-not-allowed opacity-50'">PASS</button>
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
                    <div class="space-y-2">
                        <template x-for="(player, index) in availablePlayers.slice(0, 30)" :key="player.id">
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
                                <template x-if="team.logo_path">
                                    <img :src="`/storage/${team.logo_path}`" class="w-10 h-10 rounded-full object-cover border border-gray-600">
                                </template>
                                <template x-if="!team.logo_path">
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
                                        <img :src="`/storage/${bid.team_logo}`" class="w-10 h-10 rounded-full object-cover">
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
                if (this.biddingTimerSeconds <= 0) {
                    this.stopBiddingTimer();
                }
            }, 1000);
        },

        resetBiddingTimer() {
            const resetTo = this.BID_TIMER_RESET_TO || this.BID_TIMER_DURATION;
            this.startBiddingTimer(resetTo);
        },

        stopBiddingTimer() {
            if (this.biddingTimerInterval) {
                clearInterval(this.biddingTimerInterval);
                this.biddingTimerInterval = null;
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

                this.teams = (data.teams || []).map(t => {
                    t.remaining_budget = t.remaining_budget ?? (this.maxBudget - (t.total_spent || 0));
                    return t;
                });

                const newPlayer = data.current_player;
                const prevId = this._lastCurrentPlayerId;

                if (newPlayer) {
                    if (newPlayer.id !== prevId) {
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
        async bidForTeam(teamId) {
            if (!this.currentPlayer || this.displayState !== 'bidding' || this.openBidMode === 'offline') return;
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
                        teamId: teamId
                    })
                });
                const data = await response.json();
                if (data.success) {
                    this.currentBid = data.current_price;
                    const team = this.teams.find(t => t.id == teamId);
                    if (team) this.winningTeamName = team.name;
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

            const randomIdx = Math.floor(Math.random() * this.availablePlayers.length);
            const chosenPlayer = this.availablePlayers[randomIdx];

            // Run shuffle animation
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

            const shuffleInterval = setInterval(() => {
                const randomIndex = Math.floor(Math.random() * this.availablePlayers.length);
                this.tumblerText = this.availablePlayers[randomIndex].name;
                shuffleCount++;

                if (shuffleCount >= maxShuffles) {
                    clearInterval(shuffleInterval);
                    this.selectedPlayerId = this.availablePlayers[randomIndex].id;
                    this.statusText = `Selected: ${this.availablePlayers[randomIndex].name}`;
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
                const response = await fetch(`/admin/organizer/auction/${this.auctionId}/api/${endpoint}`, {
                    method: (endpoint === 'sealed-bids' || endpoint === 'all-players') ? 'GET' : 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: (endpoint === 'sealed-bids' || endpoint === 'all-players') ? undefined : JSON.stringify(body)
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

        async sellPlayer() {
            if (!this.currentPlayer) return;
            const highestBid = this.currentPlayer.bids?.length
                ? this.currentPlayer.bids.reduce((a, b) => (a.amount > b.amount ? a : b), this.currentPlayer.bids[0])
                : null;
            this.sellModalData = {
                team_id: highestBid?.team_id || '',
                amount: highestBid?.amount || this.currentPlayer.current_price || this.currentPlayer.base_price,
            };
            this.showSellModal = true;
        },

        async passPlayer() {
            if (!this.currentPlayer) return;
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
            if (key === 'P' && this.currentPlayer && this.displayState === 'bidding') {
                e.preventDefault(); this.passPlayer(); return;
            }

            // Number keys 1-9, 0 — bid for team
            if (this.currentPlayer && this.displayState === 'bidding' && this.openBidMode !== 'offline') {
                let teamIdx = -1;
                if (e.key >= '1' && e.key <= '9') teamIdx = parseInt(e.key) - 1;
                else if (e.key === '0') teamIdx = 9;

                if (teamIdx >= 0 && teamIdx < this.teams.length) {
                    e.preventDefault();
                    this.bidForTeam(this.teams[teamIdx].id);
                }
            }
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
            const num = Number(amount) || 0;
            if (num >= 10000000) {
                const val = num / 10000000;
                return (val % 1 === 0 ? val.toFixed(0) : val.toFixed(2).replace(/\.?0+$/, '')) + ' Cr';
            }
            if (num >= 100000) {
                const val = num / 100000;
                return (val % 1 === 0 ? val.toFixed(0) : val.toFixed(2).replace(/\.?0+$/, '')) + ' L';
            }
            if (num >= 1000) {
                const val = num / 1000;
                return (val % 1 === 0 ? val.toFixed(0) : val.toFixed(1).replace(/\.?0+$/, '')) + 'K';
            }
            return num.toLocaleString();
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
