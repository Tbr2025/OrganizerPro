<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Auction Display | {{ $auction->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">
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

        .badge {
            @apply inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-600 text-white;
        }
    </style>
</head>

<body class="bg-gray-900 text-white antialiased">

    <div x-data="publicAuctionBoard()" x-init="init({{ $auction->id }})"
        class="min-h-screen flex flex-col items-center justify-center p-4 bg-cover bg-center"
        style="background-image: url('{{ asset('images/product/stadium-background.jpg') }}');">

        <div class="absolute inset-0 bg-black/70"></div>

        <div class="relative w-full max-w-5xl mx-auto z-10">
            {{-- Header --}}
            <div class="text-center mb-8">
                <img src="{{ asset('images/logo/landing.png') }}" alt="YSIPL Logo" class="w-48 mx-auto mb-2">
                <h1 class="text-4xl font-bold uppercase tracking-wider text-yellow-400">Live Player Auction</h1>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- Main Player Card Area --}}
                <div class="lg:col-span-2 space-y-6">
                    <div
                        class="bg-gray-800/80 backdrop-blur-sm rounded-2xl shadow-2xl border border-yellow-500/30 min-h-[500px] flex items-center justify-center p-6">

                        <div x-show="state === 'waiting'" x-transition.opacity>
                            <div class="text-center">
                                <h2 class="text-3xl font-bold text-gray-300 mb-4">Waiting for Next Player...</h2>
                                <div class="font-oswald text-8xl font-bold text-yellow-400" x-ref="tumbler">-----</div>
                            </div>
                        </div>

                        <div x-show="state === 'bidding'" x-transition.opacity x-cloak>
                            <div class="flex flex-col md:flex-row items-center gap-8">
                                <div class="flex-shrink-0 text-center">
                                    <img :src="player.image_url" :alt="player.name"
                                        class="w-48 h-48 object-cover rounded-full border-4 border-yellow-400 shadow-lg mx-auto">
                                    <div class="mt-3">
                                        <div class="text-sm text-gray-400">Base Price</div>
                                        <div class="text-2xl font-bold text-white"
                                            x-text="formatCurrency(player.base_price)"></div>
                                    </div>
                                </div>
                                <div class="text-center md:text-left">
                                    <h2 class="text-5xl font-bold" x-text="player.name"></h2>
                                    <div class="flex items-center justify-center md:justify-start gap-4 mt-3">
                                        <span class="badge" x-text="player.role"></span>
                                        <span class="badge" x-text="player.batting_style"></span>
                                        <span class="badge" x-text="player.bowling_style"></span>
                                        <template x-if="player.is_wicket_keeper"><span
                                                class="badge bg-indigo-500 text-white">WK</span></template>
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

                        <div x-show="state === 'sold'" x-transition.opacity x-cloak>
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

                {{-- Bidding Status & Log --}}
                <div class="lg:col-span-1">
                    {{-- ... Your existing bidding status and history log ... --}}
                </div>
            </div>
        </div>
    </div>


</body>

</html>
