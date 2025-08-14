<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Auction | {{ $auction->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Roboto', sans-serif; }
        h1, h2, h3, .font-oswald { font-family: 'Oswald', sans-serif; }
        .badge { @apply inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-600 dark:bg-gray-700 text-white; }
        .tumbler-text { transition: transform 0.1s ease-in-out; }
    </style>
</head>
<body class="bg-gray-900 text-white antialiased">

    <div 
        x-data="publicAuctionBoard()" 
        x-init="init(
            {{ $auction->id }},
            @json($currentPlayer)
        )"
        class="min-h-screen flex flex-col items-center justify-center p-4 bg-cover bg-center" 
        style="background-image: url('{{ asset('images/product/stadium-background.jpg') }}');">
        
        <div class="absolute inset-0 bg-black/70"></div>

        <div class="relative w-full max-w-5xl mx-auto z-10">
            <div class="text-center mb-8">
                <img src="{{ asset('images/logo/landing.png') }}" alt="YSIPL Logo" class="w-48 mx-auto mb-2">
                <h1 class="text-4xl font-bold uppercase tracking-wider text-yellow-400">Live Player Auction</h1>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- Main Player Card Area --}}
                <div class="lg:col-span-2 bg-gray-800/80 backdrop-blur-sm rounded-2xl shadow-2xl border border-yellow-500/30 min-h-[500px] flex items-center justify-center p-6">
                    <div x-show="state === 'waiting'" x-transition.opacity class="text-center">
                        <h2 class="text-3xl font-bold text-gray-300 mb-4" x-text="waitingText"></h2>
                        <div class="font-oswald text-8xl font-bold text-yellow-400" x-ref="tumbler" x-text="tumblerText">-----</div>
                    </div>

                    <div x-show="state === 'bidding'" x-transition.opacity x-cloak class="w-full">
                        <div class="flex flex-col md:flex-row items-center gap-8">
                            <div class="flex-shrink-0 text-center">
                                <img :src="player.image_url" :alt="player.name" class="w-48 h-48 object-cover rounded-full border-4 border-yellow-400 shadow-lg mx-auto">
                                <div class="mt-3"><div class="text-sm text-gray-400">Base Price</div><div class="text-2xl font-bold" x-text="formatCurrency(player.base_price)"></div></div>
                            </div>
                            <div class="text-center md:text-left">
                                <h2 class="text-5xl font-bold" x-text="player.name"></h2>
                                <div class="flex items-center justify-center md:justify-start flex-wrap gap-2 mt-3">
                                    <span class="badge" x-text="player.role"></span>
                                    <span class="badge" x-text="player.batting_style"></span>
                                    <span class="badge" x-text="player.bowling_style"></span>
                                    <template x-if="player.is_wicket_keeper"><span class="badge bg-indigo-500 text-white">WK</span></template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="state === 'sold'" x-transition.opacity x-cloak class="text-center">
                        <h2 class="text-6xl font-extrabold text-green-500 animate-pulse" x-text="soldStatusText"></h2>
                        <p class="text-2xl mt-4">to <strong class="text-white" x-text="winningTeamName"></strong></p>
                        <p class="text-xl mt-2">for <strong class="text-yellow-400" x-text="formatCurrency(finalPrice)"></strong></p>
                    </div>
                </div>

                {{-- Bidding Status & Log --}}
                <div class="lg:col-span-1 bg-gray-800/80 backdrop-blur-sm rounded-2xl shadow-2xl border border-yellow-500/30">
                    <div class="p-4 border-b border-gray-700">
                        <h3 class="text-xl font-bold text-center">Current Bid</h3>
                    </div>
                    <div class="p-6 text-center">
                        <div class="text-6xl font-oswald font-bold text-yellow-400 mb-2" x-text="formatCurrency(currentBid)"></div>
                        <div class="text-lg text-gray-300">Held by: <strong class="text-white" x-text="winningTeamName"></strong></div>
                    </div>
                    <div class="p-4 border-t border-gray-700">
                        <h3 class="text-lg font-semibold text-center text-gray-400">Bid History</h3>
                    </div>
                    <ul class="px-4 pb-4 space-y-2 max-h-80 overflow-y-auto">
                        <template x-for="bid in bidHistory" :key="bid.id">
                            <li class="flex justify-between items-center bg-black/20 p-2 rounded-md text-sm">
                                <span class="font-semibold" x-text="bid.team.name"></span>
                                <span class="text-yellow-400" x-text="formatCurrency(bid.amount)"></span>
                            </li>
                        </template>
                        <li x-show="bidHistory.length === 0" class="text-center text-gray-500 py-4">No bids placed yet.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function publicAuctionBoard() {
            return {
                state: 'waiting',
                player: { stats: {} }, // Default structure
                currentBid: 0,
                winningTeamName: 'No Bids',
                finalPrice: 0,
                bidHistory: [],
                waitingText: 'Waiting for Auction to Start...',
                tumblerText: '-----',
                soldStatusText: 'SOLD!',
                
                init(auctionId, currentPlayer) {
                    if (currentPlayer) {
                        this.handlePlayerOnBid({ auctionPlayer: currentPlayer });
                    }
                
                    const connectToEcho = () => {
                        if (window.Echo) {
                            console.log(`Public display connecting to Echo: auction.${auctionId}`);
                            window.Echo.private(`auction.${auctionId}`)
                                .listen('.player.onbid', (e) => this.handlePlayerOnBid(e))
                                .listen('.new-bid', (e) => this.handleNewBid(e))
                                .listen('.player.sold', (e) => this.handlePlayerSold(e));
                        } else {
                            setTimeout(connectToEcho, 100);
                        }
                    };
                    connectToEcho();
                },

                handlePlayerOnBid(event) {
                    this.player = this.formatPlayerForDisplay(event.auctionPlayer);
                    this.currentBid = parseFloat(event.auctionPlayer.current_price || event.auctionPlayer.base_price);
                    this.winningTeamName = event.auctionPlayer.current_bid_team?.name || 'No Bids';
                    this.bidHistory = event.auctionPlayer.bids.sort((a, b) => b.amount - a.amount); // Show highest first
                    this.state = 'bidding';
                },
                handleNewBid(event) {
                    this.currentBid = parseFloat(event.bid.amount);
                    this.winningTeamName = event.bid.team.name;
                    this.bidHistory.unshift(event.bid);
                },
                handlePlayerSold(event) {
                    this.state = 'sold';
                    this.finalPrice = event.auctionPlayer.final_price;
                    this.winningTeamName = event.winningTeam?.name || '---';
                    this.soldStatusText = event.winningTeam ? 'SOLD!' : 'UNSOLD';
                    
                    setTimeout(() => {
                        this.state = 'waiting';
                        this.waitingText = 'Waiting for Next Player...';
                    }, 5000); // Go back to waiting after 5 seconds
                },

                formatPlayerForDisplay(ap) { // ap = auctionPlayer
                    return {
                        name: ap.player.name,
                        image_url: ap.player.image_path ? `/storage/${ap.player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(ap.player.name)}`,
                        base_price: parseFloat(ap.base_price),
                        role: ap.player.player_type?.type ?? 'N/A',
                        batting_style: ap.player.batting_profile?.style ?? 'N/A',
                        bowling_style: ap.player.bowling_profile?.style ?? 'N/A',
                        is_wicket_keeper: ap.player.is_wicket_keeper,
                    };
                },
                formatCurrency(amount) {
                    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 0 }).format(amount || 0);
                }
            }
        }
    </script>
</body>
</html>