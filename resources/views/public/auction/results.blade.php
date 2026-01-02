<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $auction->name }} - Auction Results</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen" x-data="auctionResults()" x-init="init()">

    {{-- Header --}}
    <header class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-6 shadow-lg">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <h1 class="text-3xl md:text-4xl font-bold">{{ $auction->name }}</h1>
                <p class="text-blue-100 mt-1">{{ $auction->tournament->name ?? '' }}</p>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        {{-- Statistics Bar --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-green-500 text-white p-4 rounded-xl text-center shadow-lg transform hover:scale-105 transition">
                <div class="text-4xl font-bold" x-text="soldCount">0</div>
                <div class="text-sm uppercase tracking-wide mt-1">Sold</div>
            </div>
            <div class="bg-red-500 text-white p-4 rounded-xl text-center shadow-lg transform hover:scale-105 transition">
                <div class="text-4xl font-bold" x-text="unsoldCount">0</div>
                <div class="text-sm uppercase tracking-wide mt-1">Unsold</div>
            </div>
            <div class="bg-blue-500 text-white p-4 rounded-xl text-center shadow-lg transform hover:scale-105 transition">
                <div class="text-4xl font-bold" x-text="availableCount">0</div>
                <div class="text-sm uppercase tracking-wide mt-1">Available</div>
            </div>
            <div class="bg-purple-500 text-white p-4 rounded-xl text-center shadow-lg transform hover:scale-105 transition">
                <div class="text-4xl font-bold" x-text="players.length">0</div>
                <div class="text-sm uppercase tracking-wide mt-1">Total Players</div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-xl shadow-md p-4 mb-6">
            <div class="flex flex-col md:flex-row gap-4 items-center justify-between">
                {{-- Search --}}
                <div class="w-full md:w-1/3">
                    <input type="text" x-model="searchQuery" placeholder="Search player name..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                {{-- Status Filter --}}
                <div class="flex gap-2 flex-wrap justify-center">
                    <button @click="statusFilter = ''"
                        :class="statusFilter === '' ? 'bg-gray-800 text-white' : 'bg-gray-200 text-gray-700'"
                        class="px-4 py-2 rounded-full text-sm font-medium transition hover:opacity-80">
                        All (<span x-text="players.length"></span>)
                    </button>
                    <button @click="statusFilter = 'sold'"
                        :class="statusFilter === 'sold' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700'"
                        class="px-4 py-2 rounded-full text-sm font-medium transition hover:opacity-80">
                        Sold (<span x-text="soldCount"></span>)
                    </button>
                    <button @click="statusFilter = 'unsold'"
                        :class="statusFilter === 'unsold' ? 'bg-red-500 text-white' : 'bg-gray-200 text-gray-700'"
                        class="px-4 py-2 rounded-full text-sm font-medium transition hover:opacity-80">
                        Unsold (<span x-text="unsoldCount"></span>)
                    </button>
                    <button @click="statusFilter = 'available'"
                        :class="statusFilter === 'available' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'"
                        class="px-4 py-2 rounded-full text-sm font-medium transition hover:opacity-80">
                        Available (<span x-text="availableCount"></span>)
                    </button>
                </div>

                {{-- Team Filter --}}
                <div class="w-full md:w-1/4">
                    <select x-model="teamFilter"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Teams</option>
                        <template x-for="team in teams" :key="team.id">
                            <option :value="team.id" x-text="team.name"></option>
                        </template>
                    </select>
                </div>
            </div>
        </div>

        {{-- Results Count --}}
        <div class="mb-4 text-gray-600">
            Showing <span class="font-bold" x-text="filteredPlayers.length"></span> players
        </div>

        {{-- Player Cards Grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
            <template x-for="player in filteredPlayers" :key="player.id">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden border-2 transition-all duration-300 hover:shadow-xl hover:-translate-y-1"
                    :class="{
                        'border-green-500': player.status === 'sold',
                        'border-red-500': player.status === 'unsold',
                        'border-blue-500': player.status === 'on_auction',
                        'border-gray-300': player.status === 'waiting'
                    }">

                    {{-- Player Image --}}
                    <div class="relative">
                        <img :src="player.player.image_path ? `/storage/${player.player.image_path}` : `https://ui-avatars.com/api/?name=${encodeURIComponent(player.player.name)}&size=200&background=random`"
                            class="w-full h-36 object-cover object-top"
                            :alt="player.player.name"
                            loading="lazy">

                        {{-- Status Badge --}}
                        <div class="absolute top-2 right-2">
                            <span class="px-2 py-1 text-xs font-bold rounded-full uppercase shadow"
                                :class="{
                                    'bg-green-500 text-white': player.status === 'sold',
                                    'bg-red-500 text-white': player.status === 'unsold',
                                    'bg-blue-500 text-white animate-pulse': player.status === 'on_auction',
                                    'bg-gray-500 text-white': player.status === 'waiting'
                                }"
                                x-text="player.status === 'on_auction' ? 'LIVE' : player.status.toUpperCase()">
                            </span>
                        </div>

                        {{-- Base Price Badge --}}
                        <div class="absolute bottom-2 left-2">
                            <span class="px-2 py-1 text-xs font-bold rounded bg-black/70 text-white">
                                Base: <span x-text="formatCurrency(player.base_price)"></span>
                            </span>
                        </div>
                    </div>

                    {{-- Player Info --}}
                    <div class="p-3">
                        <h3 class="font-bold text-gray-900 text-sm truncate" x-text="player.player.name"></h3>

                        {{-- Player Type / Role --}}
                        <p class="text-xs text-gray-500 mt-1" x-text="player.player.player_type?.name || 'Player'"></p>

                        {{-- Batting & Bowling Style --}}
                        <div class="text-xs text-gray-400 mt-1 space-y-0.5">
                            <p x-show="player.player.batting_profile?.name">
                                <span class="font-medium">Bat:</span> <span x-text="player.player.batting_profile?.name"></span>
                            </p>
                            <p x-show="player.player.bowling_profile?.name">
                                <span class="font-medium">Bowl:</span> <span x-text="player.player.bowling_profile?.name"></span>
                            </p>
                        </div>

                        {{-- Divider --}}
                        <hr class="my-2 border-gray-200">

                        {{-- Sold Info --}}
                        <template x-if="player.status === 'sold'">
                            <div class="flex items-center gap-2">
                                <template x-if="player.sold_to_team?.logo_path">
                                    <img :src="`/storage/${player.sold_to_team.logo_path}`"
                                         class="w-8 h-8 rounded-full object-cover border-2 border-green-500"
                                         :alt="player.sold_to_team?.name">
                                </template>
                                <template x-if="!player.sold_to_team?.logo_path">
                                    <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center text-white text-xs font-bold"
                                         x-text="player.sold_to_team?.name?.charAt(0) || 'T'"></div>
                                </template>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-gray-500 truncate" x-text="player.sold_to_team?.name || 'Team'"></p>
                                    <p class="text-sm font-bold text-green-600" x-text="formatCurrency(player.final_price || player.current_price)"></p>
                                </div>
                            </div>
                        </template>

                        {{-- Unsold Info --}}
                        <template x-if="player.status === 'unsold'">
                            <div class="flex items-center gap-2 text-red-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                </svg>
                                <span class="text-sm font-bold">UNSOLD</span>
                            </div>
                        </template>

                        {{-- Available/On Auction Info --}}
                        <template x-if="player.status === 'on_auction' || player.status === 'waiting'">
                            <div>
                                <p class="text-xs text-gray-500">Current Bid</p>
                                <p class="text-lg font-bold text-blue-600" x-text="formatCurrency(player.current_price)"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Empty State --}}
        <div x-show="filteredPlayers.length === 0" x-cloak
            class="text-center py-20 bg-white rounded-xl shadow-md">
            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <p class="text-gray-500 text-lg">No players found matching your filters</p>
        </div>
    </main>

    {{-- Footer --}}
    <footer class="bg-gray-800 text-white py-4 mt-8">
        <div class="container mx-auto px-4 text-center">
            <p class="text-sm text-gray-400">&copy; {{ date('Y') }} {{ $auction->organization->name ?? config('app.name') }}. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function auctionResults() {
            return {
                players: @json($auction->auctionPlayers),
                teams: @json($teams),
                searchQuery: '',
                statusFilter: '',
                teamFilter: '',

                init() {
                    // Sort players: sold first, then by name
                    this.sortPlayers();
                },

                get soldCount() {
                    return this.players.filter(p => p.status === 'sold').length;
                },
                get unsoldCount() {
                    return this.players.filter(p => p.status === 'unsold').length;
                },
                get availableCount() {
                    return this.players.filter(p => ['waiting', 'on_auction'].includes(p.status)).length;
                },

                get filteredPlayers() {
                    return this.players.filter(p => {
                        const matchesSearch = this.searchQuery === '' ||
                            p.player.name.toLowerCase().includes(this.searchQuery.toLowerCase());

                        let matchesStatus = true;
                        if (this.statusFilter === 'sold') {
                            matchesStatus = p.status === 'sold';
                        } else if (this.statusFilter === 'unsold') {
                            matchesStatus = p.status === 'unsold';
                        } else if (this.statusFilter === 'available') {
                            matchesStatus = ['waiting', 'on_auction'].includes(p.status);
                        }

                        const matchesTeam = this.teamFilter === '' ||
                            (p.sold_to_team && p.sold_to_team.id == this.teamFilter);

                        return matchesSearch && matchesStatus && matchesTeam;
                    });
                },

                sortPlayers() {
                    this.players.sort((a, b) => {
                        // Sort by status priority: on_auction > sold > waiting > unsold
                        const statusPriority = { 'on_auction': 0, 'sold': 1, 'waiting': 2, 'unsold': 3 };
                        const priorityA = statusPriority[a.status] ?? 4;
                        const priorityB = statusPriority[b.status] ?? 4;
                        if (priorityA !== priorityB) return priorityA - priorityB;
                        // Then by name
                        return a.player.name.localeCompare(b.player.name);
                    });
                },

                formatCurrency(points) {
                    points = Number(points) || 0;
                    const isNegative = points < 0;
                    const absPoints = Math.abs(points);
                    let formattedValue;
                    if (absPoints >= 1000000) formattedValue = (absPoints / 1000000).toFixed(2).replace(/\.00$/, '') + 'M';
                    else if (absPoints >= 1000) formattedValue = (absPoints / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
                    else formattedValue = new Intl.NumberFormat('en-US').format(absPoints);
                    return `${isNegative ? '-' : ''}${formattedValue}`;
                }
            }
        }
    </script>
</body>
</html>
