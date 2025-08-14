<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Player Auction | YSIPL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    {{-- Include your compiled app.js which has Laravel Echo --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }

        h1,
        h2,
        h3,
        .font-oswald {
            font-family: 'Oswald', sans-serif;
        }

        .number-spinner span {
            display: inline-block;
            transition: transform 0.3s ease-in-out;
        }
    </style>
</head>

<body class="bg-gray-900 text-white antialiased">

    {{-- Main Alpine.js component that controls the entire page --}}
    <div x-data="auctionBoard()" x-init="initEcho({{ $auction->id }})"
        class="min-h-screen flex flex-col items-center justify-center p-4 bg-cover bg-center"
        style="background-image: url('{{ asset('images/product/stadium-background.jpg') }}');">

        <div class="absolute inset-0 bg-black/70"></div>

        <div class="relative w-full max-w-5xl mx-auto z-10">

            {{-- Header --}}
            <div class="text-center mb-8">
                <img src="{{ asset('images/logo/landing.png') }}" alt="YSIPL Logo" class="w-48 mx-auto mb-2">
                <h1 class="text-4xl font-bold uppercase tracking-wider text-yellow-400">Live Player Auction</h1>
            </div>

            {{-- Main Content Grid --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                {{-- LEFT/MAIN: Player Card & Bidding Info --}}
                <div class="lg:col-span-2 space-y-6">

                    <!-- Player Card Area -->
                    <div
                        class="bg-gray-800/80 backdrop-blur-sm rounded-2xl shadow-2xl border border-yellow-500/30 min-h-[500px] flex items-center justify-center p-6">

                        {{-- STATE 1: Waiting for Next Player (with Spinner) --}}
                        <div x-show="state === 'waiting'" x-transition.opacity.duration.500ms>
                            <div class="text-center">
                                <h2 class="text-3xl font-bold text-gray-300 mb-4">Selecting Next Player...</h2>
                                {{-- Casino Number Spinner --}}
                                <div class="font-oswald text-8xl font-bold text-yellow-400 tracking-widest bg-black/30 p-6 rounded-lg"
                                    x-ref="spinner" aria-live="polite">000</div>
                                <p class="text-gray-400 mt-4">Stand by for the next player to go under the hammer.</p>
                            </div>
                        </div>

                        {{-- STATE 2: Player is Live for Bidding --}}
                        <div x-show="state === 'bidding'" x-transition.opacity.duration.500ms x-cloak>
                            <div class="flex flex-col md:flex-row items-center gap-8">
                                {{-- Player Image --}}
                                <div class="flex-shrink-0 text-center">
                                    <img :src="player.image_url" :alt="player.name"
                                        class="w-48 h-48 object-cover rounded-full border-4 border-yellow-400 shadow-lg mx-auto">
                                    <div class="mt-3">
                                        <div class="text-sm text-gray-400">Base Price</div>
                                        <div class="text-2xl font-bold text-white"
                                            x-text="formatCurrency(player.base_price)"></div>
                                    </div>
                                </div>
                                {{-- Player Details --}}
                                <div class="text-center md:text-left">
                                    <h2 class="text-5xl font-bold" x-text="player.name"></h2>
                                    <div class="flex items-center justify-center md:justify-start gap-4 mt-3">
                                        <span class="badge" x-text="player.role"></span>
                                        <span class="badge" x-text="player.batting_style"></span>
                                        <span class="badge" x-text="player.bowling_style"></span>
                                        <template x-if="player.is_wicket_keeper">
                                            <span class="badge bg-indigo-500 text-white">WK</span>
                                        </template>
                                    </div>
                                    <hr class="border-gray-700 my-4">
                                    <div class="grid grid-cols-3 gap-4 text-center">
                                        <div>
                                            <div class="font-bold text-2xl" x-text="player.stats.matches"></div>
                                            <div class="text-xs text-gray-400">MATCHES</div>
                                        </div>
                                        <div>
                                            <div class="font-bold text-2xl" x-text="player.stats.runs"></div>
                                            <div class="text-xs text-gray-400">RUNS</div>
                                        </div>
                                        <div>
                                            <div class="font-bold text-2xl" x-text="player.stats.wickets"></div>
                                            <div class="text-xs text-gray-400">WICKETS</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- STATE 3: Player is Sold --}}
                        <div x-show="state === 'sold'" x-transition.opacity.duration.500ms x-cloak>
                            <div class="text-center">
                                <h2 class="text-6xl font-extrabold text-green-500 animate-pulse">SOLD!</h2>
                                <p class="text-2xl text-gray-200 mt-4">to</p>
                                <h3 class="text-4xl font-bold text-white" x-text="winningTeam"></h3>
                                <p class="text-xl text-gray-300 mt-2">for</p>
                                <div class="text-5xl font-bold text-yellow-400" x-text="formatCurrency(finalPrice)">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- RIGHT/SIDE: Bidding Status & Log --}}
                <div class="lg:col-span-1">
                    <div class="bg-gray-800/80 backdrop-blur-sm rounded-2xl shadow-2xl border border-yellow-500/30">
                        <div class="p-4 border-b border-gray-700">
                            <h3 class="text-xl font-bold text-center">Current Bid</h3>
                        </div>
                        <div class="p-6 text-center">
                            <div class="text-6xl font-oswald font-bold text-yellow-400 mb-2"
                                x-text="formatCurrency(currentBid)"></div>
                            <div class="text-lg text-gray-300">
                                Held by: <strong class="text-white" x-text="winningTeam"></strong>
                            </div>
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
                            <li x-show="bidHistory.length === 0" class="text-center text-gray-500 py-4">
                                No bids placed yet.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function auctionBoard() {
            return {
                // --- STATE MANAGEMENT ---
                state: 'waiting', // Can be 'waiting', 'bidding', 'sold'

                // --- DATA ---
                player: {},
                currentBid: 0,
                winningTeam: 'No Bids',
                finalPrice: 0,
                bidHistory: [],

                // --- METHODS ---
                initEcho(auctionId) {
                    console.log(`Connecting to auction channel: auction.${auctionId}`);
                    window.Echo.private(`auction.${auctionId}`)
                        .listen('.next-player', (e) => this.handleNextPlayer(e))
                        .listen('.new-bid', (e) => this.handleNewBid(e))
                        .listen('.player-sold', (e) => this.handlePlayerSold(e));
                },

                handleNextPlayer(event) {
                    console.log('Received next player:', event.player);
                    this.bidHistory = [];
                    this.startSpinner(event.player);
                },

                handleNewBid(event) {
                    console.log('Received new bid:', event.bid);
                    this.currentBid = event.bid.amount;
                    this.winningTeam = event.bid.team.name;
                    this.bidHistory.unshift(event.bid);
                },

                handlePlayerSold(event) {
                    console.log('Player sold:', event.player);
                    this.state = 'sold';
                    this.finalPrice = event.player.final_price;
                    this.winningTeam = event.winningTeam.name;
                },

                startSpinner(nextPlayer) {
                    this.state = 'waiting';
                    let spinner = this.$refs.spinner;
                    let count = 0;
                    let interval = setInterval(() => {
                        spinner.textContent = Math.floor(Math.random() * 900 + 100);
                        count++;
                        if (count > 20) { // Spin for ~2 seconds
                            clearInterval(interval);
                            this.displayPlayer(nextPlayer);
                        }
                    }, 100);
                },

                displayPlayer(playerData) {
                    // This is where you would format your data from the backend
                    this.player = {
                        name: playerData.player.name,
                        image_url: playerData.player.image_path ? `/storage/${playerData.player.image_path}` :
                            `https://ui-avatars.com/api/?name=${encodeURIComponent(playerData.player.name)}`,
                        base_price: playerData.base_price,
                        role: playerData.player.player_type.type,
                        batting_style: playerData.player.batting_profile.style,
                        bowling_style: playerData.player.bowling_profile.style,
                        is_wicket_keeper: playerData.player.is_wicket_keeper,
                        stats: {
                            matches: playerData.player.total_matches || 0,
                            runs: playerData.player.total_runs || 0,
                            wickets: playerData.player.total_wickets || 0,
                        }
                    };
                    this.currentBid = playerData.base_price;
                    this.winningTeam = 'No Bids';
                    this.state = 'bidding';
                },

                formatCurrency(amount) {
                    // Simple currency formatter, you can make this more complex (e.g., for Millions, Lakhs)
                    return new Intl.NumberFormat('en-IN', {
                        style: 'currency',
                        currency: 'INR',
                        minimumFractionDigits: 0
                    }).format(amount);
                }
            }
        }
    </script>
</body>

</html>
