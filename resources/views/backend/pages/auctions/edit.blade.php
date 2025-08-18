@extends('backend.layouts.app')

@section('title', 'Edit Auction | ' . $auction->name)

@section('admin-content')
    <div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Edit Auction Configuration</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Editing: <span
                        class="font-semibold">{{ $auction->name }}</span></p>
            </div>
        </div>

        {{-- Main Form Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700"
            x-data="auctionForm({{ json_encode($auction) }}, {{ json_encode($availablePlayers) }}, {{ json_encode($organizations) }}, {{ json_encode($tournaments) }})" x-init="init()">

            <form action="{{ route('admin.auctions.update', $auction) }}" method="POST" @submit.prevent="submitForm"
                x-ref="auctionFormElement">
                @csrf
                @method('PUT')

                {{-- Step Navigation --}}
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex flex-wrap space-x-4" aria-label="Tabs">
                        {{-- Step Buttons with dynamic classes for active state and transitions --}}
                        <button type="button" @click="step = 1"
                            :class="{
                                'border-blue-500 text-blue-600 dark:text-blue-400 font-bold': step === 1,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !== 1
                            }"
                            class="px-3 py-2 font-medium text-sm rounded-md border-b-2 transition-colors duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            1. Details
                        </button>
                        <button type="button" @click="step = 2"
                            :class="{
                                'border-blue-500 text-blue-600 dark:text-blue-400 font-bold': step === 2,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !== 2
                            }"
                            class="px-3 py-2 font-medium text-sm rounded-md border-b-2 transition-colors duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            2. Financials
                        </button>
                        <button type="button" @click="step = 3"
                            :class="{
                                'border-blue-500 text-blue-600 dark:text-blue-400 font-bold': step === 3,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !== 3
                            }"
                            class="px-3 py-2 font-medium text-sm rounded-md border-b-2 transition-colors duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            3. Bid Rules
                        </button>
                        <button type="button" @click="step = 4"
                            :class="{
                                'border-blue-500 text-blue-600 dark:text-blue-400 font-bold': step === 4,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !== 4
                            }"
                            class="px-3 py-2 font-medium text-sm rounded-md border-b-2 transition-colors duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            4. Player Pool
                        </button>
                    </nav>
                </div>

                <div class="p-6">
                    {{-- Step 1: Auction Details --}}
                    <div x-show="step === 1" x-transition:enter.duration.300ms.opacity
                        x-transition:leave.duration.300ms.opacity>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="form-label">Auction Name <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="name" id="name" x-model="auctionData.name"
                                    class="form-control" required>
                            </div>
                            {{-- Organization Select (Only if Superadmin) --}}
                            @if (auth()->user()->hasRole('Superadmin'))
                                <div>
                                    <label for="organization_id" class="form-label">Organization <span
                                            class="text-red-500">*</span></label>
                                    <select name="organization_id" id="organization_id" class="form-control" required>
                                        @foreach ($organizations as $org)
                                            <option value="{{ $org->id }}"
                                                {{ old('organization_id', $auction->organization_id) == $org->id ? 'selected' : '' }}>
                                                {{ $org->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                {{-- If not Superadmin, organization is fixed, just store it --}}
                                <input type="hidden" name="organization_id" x-model="auctionData.organization_id">
                            @endif
                            <div>
                                <label for="tournament_id" class="form-label">Tournament <span
                                        class="text-red-500">*</span></label>
                                <select name="tournament_id" id="tournament_id" class="form-control" required>
                                    @foreach ($tournaments as $t)
                                        <option value="{{ $t->id }}"
                                            {{ old('tournament_id', $auction->tournament_id) == $t->id ? 'selected' : '' }}>
                                            {{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-control" x-model="auctionData.status">
                                    <option value="scheduled">Pending</option>
                                    <option value="running">Live</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Step 2: Financial Rules --}}
                    <div x-show="step === 2" x-transition:enter.duration.300ms.opacity
                        x-transition:leave.duration.300ms.opacity>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="max_budget_per_team" class="form-label">Max Budget Per Team <span
                                        class="text-red-500">*</span></label>
                                <input type="number" name="max_budget_per_team" id="max_budget_per_team"
                                    class="form-control" required x-model.number="auctionData.max_budget_per_team">
                            </div>
                            <div>
                                <label for="base_price" class="form-label">Default Player Base Price <span
                                        class="text-red-500">*</span></label>
                                <input type="number" name="base_price" id="base_price" class="form-control" required
                                    x-model.number="auctionData.base_price">
                            </div>
                        </div>
                    </div>

                    {{-- Step 3: Bid Increment Rules --}}
                    <div x-show="step === 3" x-transition:enter.duration.300ms.opacity
                        x-transition:leave.duration.300ms.opacity>
                        <div>
                            <h3 class="font-semibold text-lg mb-3">Configure Bid Increments</h3>
                            <div class="space-y-3">
                                <template x-for="(rule, index) in rules" :key="index">
                                    <div
                                        class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 relative">
                                        <span class="text-gray-500">From</span>
                                        <input type="number" :name="`bid_rules[${index}][from]`" x-model.number="rule.from"
                                            class="form-control w-24" required min="0">
                                        <span class="text-gray-500">To</span>
                                        <input type="number" :name="`bid_rules[${index}][to]`" x-model.number="rule.to"
                                            class="form-control w-24" required min="0">
                                        <span class="text-gray-500">Increment</span>
                                        <input type="number" :name="`bid_rules[${index}][increment]`"
                                            x-model.number="rule.increment" class="form-control w-24" required
                                            min="0">
                                        <button type="button" @click="removeRule(index)"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-red-500 hover:text-red-700 font-bold text-lg px-2 py-1">
                                            &times;
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="addRule()"
                                class="btn btn-secondary mt-4 transition-transform duration-150 hover:scale-105">
                                + Add Rule
                            </button>
                        </div>
                    </div>

                    {{-- Step 4: Player Pool Management (AJAX Handled) --}}
                    <div x-show="step === 4" x-transition:enter.duration.300ms.opacity
                        x-transition:leave.duration.300ms.opacity>
                        <h3 class="font-semibold text-lg mb-3">Manage Auction Player Pool</h3>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            {{-- Players IN Auction Pool --}}
                            <div
                                class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg shadow-inner border border-gray-200 dark:border-gray-700">
                                <div class="flex justify-between items-center mb-3">
                                    <h4 class="font-semibold text-md">Players in Pool (<span
                                            x-text="inAuction.length"></span>)</h4>
                                    <input type="text" x-model="searchInPool" placeholder="Search..."
                                        class="form-control form-control-sm w-1/2">
                                </div>
                                {{-- List of players in the pool --}}
                                <div
                                    class="space-y-2 max-h-96 overflow-y-auto border p-3 rounded bg-white dark:bg-gray-800 shadow-sm">
                                    <template x-for="player in inAuctionFiltered" :key="player.id">
                                        <div
                                            class="flex justify-between items-center p-2 bg-white dark:bg-gray-800 rounded shadow-sm border border-gray-200 dark:border-gray-700
                                             transition-shadow duration-150 hover:shadow-md">
                                            <span x-text="player.name" class="flex-1 mr-2 truncate"></span>
                                            <div class="flex items-center gap-2">
                                                {{-- Input for player's base price --}}
                                                <input type="number" :name="`player_base_prices[${player.id}]`"
                                                    x-model.number="player.base_price"
                                                    class="form-control form-control-sm w-28" placeholder="Base Price"
                                                    min="0" @input.debounce.500ms="updatePlayerPrice(player)">
                                                {{-- Remove Player Button --}}
                                                <button type="button" @click="removeFromAuction(player)"
                                                    class="text-red-500 hover:text-red-700 font-bold text-lg px-2 py-1 transition-colors duration-150">
                                                    &times;
                                                </button>
                                                {{-- Hidden inputs will be dynamically added by prepareFormSubmit() --}}
                                            </div>
                                        </div>
                                    </template>
                                    {{-- Message if no players in pool or no matches --}}
                                    <div x-show="inAuctionFiltered.length === 0" class="text-center text-gray-500 py-4">
                                        {{-- Text changes based on whether search is active or pool is empty --}}
                                        <span
                                            x-text="searchInPool ? 'No players found matching your search.' : 'No players added to the pool yet.'"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Available Players List --}}
                            <div
                                class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg shadow-inner border border-gray-200 dark:border-gray-700">
                                <div class="flex justify-between items-center mb-3">
                                    <h4 class="font-semibold text-md">Available Players</h4>
                                    <input type="text" x-model="search" placeholder="Search..."
                                        class="form-control form-control-sm w-1/2">
                                </div>
                                {{-- List of available players --}}
                                <div
                                    class="space-y-2 max-h-96 overflow-y-auto border p-3 rounded bg-white dark:bg-gray-800 shadow-sm">
                                    <template x-for="player in filteredAvailable" :key="player.id">
                                        <div
                                            class="flex justify-between items-center p-2 bg-white dark:bg-gray-800 rounded shadow-sm border border-gray-200 dark:border-gray-700
                                             transition-shadow duration-150 hover:shadow-md">
                                            <span x-text="player.name" class="flex-1 mr-2 truncate"></span>
                                            <button type="button" @click="addToAuction(player)"
                                                class="text-green-500 hover:text-green-700 font-bold text-lg px-2 py-1 transition-colors duration-150">
                                                +
                                            </button>
                                        </div>
                                    </template>
                                    {{-- Message if no available players or no matches --}}
                                    <div x-show="filteredAvailable.length === 0" class="text-center text-gray-500 py-4">
                                        {{-- Text changes based on whether search is active or pool is empty --}}
                                        <span
                                            x-text="search ? 'No players found matching your search.' : 'No more available players.'"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Form Footer with Navigation and Submit --}}
                <div
                    class="p-6 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <a href="{{ route('admin.auctions.index') }}"
                            class="btn btn-secondary transition-colors duration-150 hover:bg-gray-600">Cancel</a>
                    </div>
                    <div class="flex items-center space-x-3">
                        {{-- Previous Button --}}
                        <button type="button" @click="step--" x-show="step > 1"
                            class="btn btn-secondary transition-colors duration-150 hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="step === 1" x-cloak>
                            Previous
                        </button>
                        {{-- Next Button --}}
                        <button type="button" @click="step++" x-show="step < 4"
                            class="btn btn-primary transition-colors duration-150 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="step === 4" x-cloak>
                            Next
                        </button>
                        {{-- Submit Button (only visible on the last step) --}}
                        <button type="button" @click="submitForm()" x-show="step === 4"
                            class="btn btn-success transition-colors duration-150 hover:bg-green-700" x-cloak>
                            Update Auction & Pool
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Ensure Alpine.js is initialized and define our component for auction form management
        document.addEventListener('alpine:init', () => {
            Alpine.data('auctionForm', (auctionData, availablePlayersData, organizationsData, tournamentsData) => ({
                // --- State Variables ---
                step: 1, // Current step in the wizard (1 to 4)

                // Main auction data, used for form inputs and initial values
                auctionData: {
                    ...auctionData
                },

                // Bid increment rules: array of objects { from, to, increment }
                rules: [],

                // Players currently in the auction pool: array of { id, name, base_price }
                inAuction: [],

                // All available players: array of { id, name }
                available: [],

                // Search terms for filtering lists
                search: '',
                searchInPool: '',

                // Temporary messages for AJAX feedback
                feedbackMessage: '',
                feedbackType: '', // 'success' or 'error'

                // --- Initialization ---
                init() {
                    // Populate bid rules: ensure there's at least one rule object
                    this.rules = auctionData.bid_rules && auctionData.bid_rules.length > 0 ?
                        auctionData.bid_rules.map(rule => ({
                            ...rule
                        })) // Clone rules to avoid modifying original data
                        :
                        [{
                            from: 0,
                            to: '',
                            increment: ''
                        }]; // Start with one empty rule if none exist

                    // Populate inAuction and available players from backend data
                    this.inAuction = auctionData.auction_players.map(ap => ({
                        id: ap.player.id,
                        name: ap.player.name,
                        base_price: ap
                            .base_price // Keep base_price for UI display and form submission
                    }));

                    this.available = availablePlayersData.map(p => ({
                        id: p.id,
                        name: p.name
                    }));

                    // Filter out players already in the pool from the available list
                    this.available = this.available.filter(p => !this.inAuction.some(inPoolPlayer =>
                        inPoolPlayer.id === p.id));

                    // Sort lists alphabetically for better user experience
                    this.available.sort((a, b) => a.name.localeCompare(b.name));
                    this.inAuction.sort((a, b) => a.name.localeCompare(b.name));
                },

                // --- Getters for Filtered Lists ---
                get inAuctionFiltered() {
                    if (!this.searchInPool) return this.inAuction;
                    return this.inAuction.filter(player =>
                        player.name.toLowerCase().includes(this.searchInPool.toLowerCase())
                    );
                },
                get filteredAvailable() {
                    if (!this.search) return this.available;
                    return this.available.filter(p => p.name.toLowerCase().includes(this.search
                        .toLowerCase()));
                },

                // --- Methods for Player Pool Management ---

                // Add a bid increment rule
                addRule() {
                    const lastRuleTo = this.rules.length > 0 ? this.rules[this.rules.length - 1].to : 0;
                    this.rules.push({
                        from: lastRuleTo,
                        to: '',
                        increment: ''
                    });
                },

                // Remove a bid increment rule
                removeRule(index) {
                    this.rules.splice(index, 1);
                },

                // Add player to the auction pool via AJAX POST request
                async addToAuction(player) {
                    // Find the player in the original available list to set its loading state
                    const availablePlayer = this.available.find(p => p.id === player.id);
                    if (!availablePlayer) return; // Should not happen if logic is correct

                    if (availablePlayer.is_loading) return; // Prevent double clicks
                    availablePlayer.is_loading =
                        true; // Set loading state for the available player item

                    const defaultBasePrice = this.auctionData.base_price;

                    try {
                        const response = await fetch(
                            `/admin/auctions/{{ $auction->id }}/players/${player.id}`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector(
                                        'meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify({
                                    base_price: defaultBasePrice
                                })
                            });

                        const responseData = await response.json();

                        if (!response.ok) {
                            this.showFeedback('Error adding player: ' + (responseData.error ||
                                response.statusText), 'error');
                            availablePlayer.is_loading = false; // Reset loading state on error
                            return;
                        }

                        // Success: Update UI state
                        // Add to inAuction list
                        this.inAuction.push({
                            id: player.id,
                            name: player.name,
                            base_price: responseData.player
                                .base_price, // Use price from response
                            is_loading: false // Ensure new item doesn't show loading
                        });
                        this.inAuction.sort((a, b) => a.name.localeCompare(b.name)); // Keep sorted

                        // Remove from available list
                        this.available = this.available.filter(p => p.id !== player.id);
                        this.search = ''; // Clear search

                        this.showFeedback('Player added successfully!', 'success');

                    } catch (error) {
                        this.showFeedback('Network error adding player.', 'error');
                        availablePlayer.is_loading = false; // Reset loading state on network error
                        console.error('Network error:', error);
                    }
                },
                // Remove player from the auction pool via AJAX DELETE request
                async removeFromAuction(player) {
                    if (!confirm(`Are you sure you want to remove ${player.name} from the pool?`)) {
                        return; // User cancelled
                    }

                    try {
                        const response = await fetch(
                            `/admin/auctions/{{ $auction->id }}/players/${player.id}`, {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector(
                                        'meta[name="csrf-token"]').getAttribute('content')
                                }
                            });

                        const responseData = await response.json();

                        if (!response.ok) {
                            this.showFeedback('Error removing player: ' + (responseData.error ||
                                response.statusText), 'error');
                            console.error('API Error Response:', responseData);
                            return;
                        }

                        // Success: Update UI state
                        this.available.push({
                            id: player.id,
                            name: player.name
                        });
                        this.available.sort((a, b) => a.name.localeCompare(b.name));
                        this.inAuction = this.inAuction.filter(p => p.id !== player.id);
                        this.searchInPool = ''; // Clear search
                        this.showFeedback('Player removed successfully!', 'success');

                    } catch (error) {
                        this.showFeedback('Network error removing player.', 'error');
                        console.error('Network error:', error);
                    }
                },

                // Update player's base price via AJAX PUT request (optional, but good for real-time updates)
                // Debounced to avoid too many requests while typing.
                async updatePlayerPrice(player) {
                    // Only update if the player is actually in the pool (has an ID)
                    if (!player.id || !this.inAuction.some(p => p.id === player.id)) {
                        return; // Should not happen with current logic, but safety check
                    }

                    try {
                        const response = await fetch(
                            `/admin/auctions/{{ $auction->id }}/players/${player.id}`, {
                                method: 'PUT', // Use PUT for update
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector(
                                        'meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify({
                                    base_price: player.base_price
                                })
                            });

                        const responseData = await response.json();

                        if (!response.ok) {
                            this.showFeedback(`Error updating price for ${player.name}: ` + (
                                responseData.error || response.statusText), 'error');
                            console.error('API Error Response:', responseData);
                            // Optionally revert the price in UI if API fails
                            // player.base_price = ... original price ...
                            return;
                        }

                        // Success feedback
                        this.showFeedback(`Price updated for ${player.name}.`, 'success');
                        console.log(`Price updated for ${player.name}:`, responseData.message);

                    } catch (error) {
                        this.showFeedback(`Network error updating price for ${player.name}.`,
                            'error');
                        console.error('Network error:', error);
                    }
                },


                // Helper to show temporary feedback messages
                showFeedback(message, type) {
                    this.feedbackMessage = message;
                    this.feedbackType = type;
                    // Clear the message after a few seconds
                    setTimeout(() => {
                        this.feedbackMessage = '';
                        this.feedbackType = '';
                    }, 3000); // Show for 3 seconds
                },

                // Prepare the form data for submission when the main form is submitted.
                // This method adds hidden inputs for player_ids and their base_prices
                // based on the current state of 'inAuction'.
                prepareFormSubmit() {
                    // Remove any old hidden inputs that might have been added from previous submissions
                    document.querySelectorAll('input[name="player_ids[]"]').forEach(el => el.remove());
                    document.querySelectorAll('input[name^="player_base_prices["]').forEach(el => el
                        .remove());

                    // Add hidden inputs for players currently in the pool
                    this.inAuction.forEach(player => {
                        // Add hidden input for player ID
                        const playerIdInput = document.createElement('input');
                        playerIdInput.type = 'hidden';
                        playerIdInput.name = 'player_ids[]';
                        playerIdInput.value = player.id;
                        this.$el.appendChild(playerIdInput); // $el refers to the form element

                        // Add hidden input for player base price
                        const playerPriceInput = document.createElement('input');
                        playerPriceInput.type = 'hidden';
                        // The name format player_base_prices[player_id] is crucial for Laravel to parse it correctly
                        playerPriceInput.name = `player_base_prices[${player.id}]`;
                        playerPriceInput.value = player.base_price;
                        this.$el.appendChild(playerPriceInput);
                    });
                },

                // Handle the main form submission (including preparing hidden fields)
                submitForm() {
                    this.prepareFormSubmit(); // Ensure player data is included

                    // Access the form element using $refs
                    if (this.$refs.auctionFormElement) {
                        this.$refs.auctionFormElement.submit(); // Submit the form
                    } else {
                        console.error("Could not find the form element by x-ref='auctionFormElement'.");
                        // Optionally, fall back to a less ideal method or show an error
                    }
                }
            }));
        });
    </script>
@endpush
