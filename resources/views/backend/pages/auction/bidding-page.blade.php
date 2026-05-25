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
                    @if($userTeam->logo_path)
                        <img src="/storage/{{ $userTeam->logo_path }}" alt="{{ $userTeam->name }}" class="w-8 h-8 rounded-full object-cover ring-2 ring-gray-700 flex-shrink-0">
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
                              'bg-purple-500/15 text-purple-400 border border-purple-500/25': bidType === 'closed' && auctionMode !== 'offline',
                              'bg-orange-500/15 text-orange-400 border border-orange-500/25': auctionMode === 'offline'
                          }"
                          x-text="auctionMode === 'offline' ? 'OFFLINE' : (bidType === 'closed' ? 'SEALED' : 'OPEN')"></span>
                </div>

                {{-- Right: Budget + Controls --}}
                <div class="flex items-center gap-3 flex-shrink-0">
                    <div class="text-right">
                        <div class="text-[9px] text-gray-500 uppercase tracking-wider leading-none">Budget</div>
                        <div class="text-sm font-bold leading-tight"
                             :class="teamBudget < {{ $auction->max_budget_per_team ?? 10000000 }} * 0.2 ? 'text-red-400' : 'text-emerald-400'"
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
                        {{-- Timer --}}
                        <div x-show="timerSeconds > 0 || timerExpired" class="mb-2">
                            <span class="text-base font-bold font-mono"
                                  :class="timerExpired ? 'text-red-500' : (timerSeconds <= 5 ? 'text-red-500 timer-critical' : 'text-white')"
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

                        <div x-show="myBidAmount > 0 && bidType === 'closed'" class="mt-2.5 pt-2.5 border-t border-gray-800/60">
                            <div class="text-[9px] text-gray-500 uppercase tracking-wider">Your Sealed Bid</div>
                            <div class="text-sm font-bold text-cyan-400 mt-0.5" x-text="formatCurrency(myBidAmount)"></div>
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
                                <span x-show="!isMyTeamHighest && !canRaiseHand && !timerExpired && nextBidAmount > teamBudget">BUDGET EXCEEDED</span>
                                <span x-show="!isMyTeamHighest && !canRaiseHand && !timerExpired && nextBidAmount <= teamBudget && nextBidAmount <= 0">WAITING...</span>
                            </span>
                            <span x-show="isSubmitting">Placing Bid...</span>
                        </button>

                        <p x-show="canRaiseHand && !isMyTeamHighest" class="text-center text-gray-600 text-[10px] mt-2">
                            Press <kbd class="px-1 py-px bg-gray-800 rounded text-gray-400 font-mono text-[9px]">Space</kbd> or <kbd class="px-1 py-px bg-gray-800 rounded text-gray-400 font-mono text-[9px]">Enter</kbd> to bid
                        </p>
                    </div>

                    {{-- CLOSED BID CONTROLS --}}
                    <div x-show="bidType === 'closed' && auctionMode !== 'offline'">
                        <div class="bg-gray-900/60 border border-gray-800/60 rounded-lg p-3 mb-2.5">
                            <label class="text-gray-400 text-[10px] uppercase tracking-wider mb-1.5 block text-center">
                                Bid Amount (Lakhs) &middot; Min: <span x-text="(closedBidMinimum / 100000)"></span> L
                            </label>
                            <div class="flex items-center gap-2">
                                <button @click="customAmount = Math.max(closedBidMinimum, (Number(customAmount) || closedBidMinimum) - currentIncrement)"
                                        class="w-9 h-9 rounded-md bg-red-500/15 border border-red-500/25 text-red-400 text-base font-bold flex items-center justify-center hover:bg-red-500/25 transition shrink-0">&minus;</button>
                                <div class="flex-1">
                                    <div class="flex items-center bg-gray-800/80 border border-gray-700/50 rounded-md focus-within:border-cyan-500/60">
                                        <input type="number"
                                               :value="Number(customAmount) > 0 ? (customAmount / 100000) : ''"
                                               @input="customAmount = Number($event.target.value) * 100000"
                                               :min="closedBidMinimum / 100000"
                                               class="w-full px-2.5 py-2 bg-transparent text-white text-base text-center focus:outline-none font-bold"
                                               :placeholder="(closedBidMinimum / 100000)"
                                               step="0.5">
                                        <span class="pr-2.5 text-gray-500 font-semibold text-xs">L</span>
                                    </div>
                                </div>
                                <button @click="customAmount = (Number(customAmount) || closedBidMinimum) + currentIncrement"
                                        class="w-9 h-9 rounded-md bg-green-500/15 border border-green-500/25 text-green-400 text-base font-bold flex items-center justify-center hover:bg-green-500/25 transition shrink-0">+</button>
                            </div>
                        </div>

                        <button @click="placeCustomBid()"
                                :disabled="!canBidCustom"
                                class="w-full py-3 rounded-lg font-bold text-sm transition-all"
                                :class="canBidCustom
                                    ? 'bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white shadow-lg shadow-green-500/20'
                                    : 'bg-gray-800/60 border border-gray-700/40 text-gray-500 cursor-not-allowed'">
                            <span x-text="isSubmitting ? 'Submitting...' : (myBidAmount > 0 ? 'UPDATE BID' : 'PLACE BID')"></span>
                        </button>
                        <p x-show="canBidCustom" class="text-center text-gray-600 text-[10px] mt-2">
                            Press <kbd class="px-1 py-px bg-gray-800 rounded text-gray-400 font-mono text-[9px]">Enter</kbd> to submit
                        </p>
                    </div>

                    {{-- OFFLINE MODE --}}
                    <div x-show="auctionMode === 'offline'" class="text-center">
                        <div class="bg-orange-500/10 border border-orange-500/25 rounded-lg p-3.5">
                            <div class="flex items-center justify-center gap-1.5 mb-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                                <span class="text-orange-400 text-[11px] font-bold uppercase tracking-wider">Offline Bidding</span>
                            </div>
                            <p class="text-gray-400 text-[11px]">The organizer is handling bids manually.</p>
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
                         :class="teamBudget < {{ $auction->max_budget_per_team ?? 10000000 }} * 0.2 ? 'text-red-400' : 'text-emerald-400'"
                         x-text="formatCurrency(teamBudget)"></div>
                    <div class="w-full bg-gray-700/40 rounded-full h-1 mt-2">
                        <div class="h-1 rounded-full transition-all duration-500"
                             :class="teamBudget < {{ $auction->max_budget_per_team ?? 10000000 }} * 0.2 ? 'bg-red-500' : 'bg-emerald-500'"
                             :style="`width: ${(teamBudget / {{ $auction->max_budget_per_team ?? 10000000 }}) * 100}%`"></div>
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
        player: { id: null, name: "", image_url: "", base_price: 0, current_price: 0, current_bid_team: null, role: "", batting_style: "", bowling_style: "", total_matches: null, total_runs: null, total_wickets: null },
        soldPlayers: @json($soldPlayers ?? []),
        state: "waiting",
        auctionStatus: "{{ $auction->status }}",
        bidType: "{{ $auction->bid_type ?? 'open' }}",
        teamBudget: {{ $remainingBudget ?? 0 }},
        bidError: "",
        bidSuccess: "",
        isSubmitting: false,
        lastPlayerId: null,
        customAmount: "",
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

        startPolling() {
            this.fetchCurrentPlayer();
            setInterval(() => {
                this.fetchCurrentPlayer();
                this.fetchSoldPlayers();
            }, 2000);
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
                        this.customAmount = "";
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
                        this.soldTeamLogo = soldEntry.sold_to_team?.logo_path ? '/storage/' + soldEntry.sold_to_team.logo_path : null;
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
            this.customAmount = "";
            this.bidSuccess = "";
        },

        get nextBidAmount() {
            const current = this.player.current_price || this.player.base_price || 0;
            const rules = this.bidRules || [];
            let increment = 0;
            for (const r of rules) {
                const from = Number(r.from) || 0;
                const to = Number(r.to) || Infinity;
                const inc = Number(r.increment) || 0;
                if (current >= from && current <= to) { increment = inc; break; }
            }
            if (increment === 0) {
                for (const r of rules) {
                    const from = Number(r.from) || 0;
                    const inc = Number(r.increment) || 0;
                    if (current < from) { increment = inc; break; }
                }
            }
            return increment > 0 ? current + increment : 0;
        },

        get canRaiseHand() {
            if (this.auctionMode === "offline") return false;
            if (this.state !== "bidding" || this.isSubmitting) return false;
            if (this.timerExpired) return false;
            if (this.isMyTeamHighest) return false;
            if (this.nextBidAmount <= 0) return false;
            if (this.nextBidAmount > this.teamBudget) return false;
            return true;
        },

        get closedBidMinimum() {
            return this.nextBidAmount > 0 ? this.nextBidAmount : this.player.base_price;
        },

        get currentIncrement() {
            const current = this.player.current_price || this.player.base_price || 0;
            const rules = this.bidRules || [];
            for (const r of rules) {
                const from = Number(r.from) || 0;
                const to = Number(r.to) || Infinity;
                const inc = Number(r.increment) || 0;
                if (current >= from && current <= to) return inc;
            }
            for (const r of rules) {
                const from = Number(r.from) || 0;
                const inc = Number(r.increment) || 0;
                if (current < from) return inc;
            }
            return 10000;
        },

        get canBidCustom() {
            if (this.auctionMode === "offline") return false;
            if (this.state !== "bidding" || this.isSubmitting) return false;
            if (this.timerExpired) return false;
            const amt = Number(this.customAmount) || 0;
            if (amt <= 0 || amt > this.teamBudget || amt < this.closedBidMinimum) return false;
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
                this._flashBid();
            } catch (e) { this.bidError = e.message; }
            finally { this.isSubmitting = false; }
        },

        async placeCustomBid() {
            if (!this.canBidCustom || !this.player.id) return;
            const amt = Number(this.customAmount);
            this.isSubmitting = true;
            this.bidError = "";
            this.bidSuccess = "";
            try {
                const res = await fetch("/admin/team/auction/" + this.auctionId + "/api/place-bid", {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content, "Accept": "application/json" },
                    body: JSON.stringify({ auction_player_id: this.player.id, amount: amt })
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || "Failed to place bid");
                this.myBidAmount = amt;
                this.bidSuccess = "Bid placed successfully!";
                this.customAmount = "";
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

        syncTimerFromServer(data) {
            const serverTime = data.server_time || 0;
            const playerUpdatedAt = data.player_updated_at || 0;
            const timerDuration = data.bid_timer_seconds || this.BID_TIMER_DURATION;
            if (!serverTime || !playerUpdatedAt) return;
            const isTimerReset = playerUpdatedAt !== this.lastServerUpdatedAt;
            this.lastServerUpdatedAt = playerUpdatedAt;
            const elapsed = serverTime - playerUpdatedAt;
            const remaining = Math.max(0, timerDuration - elapsed);
            if (remaining <= 0) {
                this.stopTimer(); this.timerSeconds = 0; this.timerWidth = 0; this.timerExpired = true;
            } else if (isTimerReset || !this.timerInterval) {
                this.startTimer(remaining, timerDuration);
            }
        },

        startTimer(remaining, maxDuration) {
            this.stopTimer();
            this.timerSeconds = Math.ceil(remaining);
            const maxSeconds = maxDuration || remaining;
            this.timerWidth = Math.max(0, (this.timerSeconds / maxSeconds) * 100);
            this.timerExpired = false;
            this.timerInterval = setInterval(() => {
                this.timerSeconds--;
                this.timerWidth = Math.max(0, (this.timerSeconds / maxSeconds) * 100);
                if (this.timerSeconds <= 0) { this.stopTimer(); this.timerExpired = true; }
            }, 1000);
        },

        stopTimer() {
            if (this.timerInterval) { clearInterval(this.timerInterval); this.timerInterval = null; }
        },

        _flashBid() {
            this.bidJustChanged = false;
            if (this._bidFlashTimeout) clearTimeout(this._bidFlashTimeout);
            setTimeout(() => {
                this.bidJustChanged = true;
                this._bidFlashTimeout = setTimeout(() => { this.bidJustChanged = false; }, 400);
            }, 10);
        },

        formatCurrency(amt) {
            const n = Number(amt) || 0;
            if (n >= 10000000) { const v = n / 10000000; return (v % 1 === 0 ? v.toFixed(0) : v.toFixed(2).replace(/\.?0+$/, "")) + " Cr"; }
            if (n >= 100000) { const v = n / 100000; return (v % 1 === 0 ? v.toFixed(0) : v.toFixed(2).replace(/\.?0+$/, "")) + " L"; }
            if (n >= 1000) { const v = n / 1000; return (v % 1 === 0 ? v.toFixed(0) : v.toFixed(1).replace(/\.?0+$/, "")) + "K"; }
            return n.toLocaleString();
        }
    };
}
</script>
@endpush
