@extends('backend.layouts.app')

@section('title', 'Edit Auction | ' . $auction->name)

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Edit Auction Configuration</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Editing: <span class="font-semibold">{{ $auction->name }}</span></p>
        </div>
    </div>

    {{-- Main Form Card with Alpine.js for Wizard Steps --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700"
         x-data="{ 
            step: 1,
            rules: {{ json_encode(old('bid_rules', $auction->bid_rules ?? [])) }},
            inAuction: {{ json_encode($auction->auctionPlayers->map(fn($ap) => ['id' => $ap->player->id, 'name' => $ap->player->name, 'base_price' => $ap->base_price])) }},
            available: {{ json_encode($availablePlayers->map(fn($p) => ['id' => $p->id, 'name' => $p->name])) }},
            search: '',
            get filteredAvailable() {
                if (this.search === '') return this.available;
                return this.available.filter(p => p.name.toLowerCase().includes(this.search.toLowerCase()));
            },
            addToAuction(player) {
                this.inAuction.push({ ...player, base_price: {{ $auction->base_price }} });
                this.available = this.available.filter(p => p.id !== player.id);
            },
            removeFromAuction(player) {
                this.available.push({ id: player.id, name: player.name });
                this.inAuction = this.inAuction.filter(p => p.id !== player.id);
                this.available.sort((a, b) => a.name.localeCompare(b.name));
            }
         }">

        <form action="{{ route('admin.auctions.update', $auction) }}" method="POST">
            @csrf
            @method('PUT')
            
            {{-- Step Navigation --}}
            <div class.blade.php="p-4 border-b border-gray-200 dark:border-gray-700">
                <nav class="flex flex-wrap space-x-4" aria-label="Tabs">
                    <a href="#" @click.prevent="step = 1" :class="{ 'border-blue-500 text-blue-600 dark:text-blue-400': step === 1, 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !== 1 }" class="px-3 py-2 font-medium text-sm rounded-md border-b-2">1. Details</a>
                    <a href="#" @click.prevent="step = 2" :class="{ 'border-blue-500 text-blue-600 dark:text-blue-400': step === 2, 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !== 2 }" class="px-3 py-2 font-medium text-sm rounded-md border-b-2">2. Financials</a>
                    <a href="#" @click.prevent="step = 3" :class="{ 'border-blue-500 text-blue-600 dark:text-blue-400': step === 3, 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !== 3 }" class="px-3 py-2 font-medium text-sm rounded-md border-b-2">3. Bid Rules</a>
                    <a href="#" @click.prevent="step = 4" :class="{ 'border-blue-500 text-blue-600 dark:text-blue-400': step === 4, 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !== 4 }" class="px-3 py-2 font-medium text-sm rounded-md border-b-2">4. Player Pool</a>
                </nav>
            </div>

            <div class="p-6">
                {{-- Step 1: Auction Details --}}
                <div x-show="step === 1" x-transition.opacity class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="form-label">Auction Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name', $auction->name) }}" class="form-control" required>
                        </div>
                        @if(auth()->user()->hasRole('Superadmin'))
                        <div>
                            <label for="organization_id" class="form-label">Organization <span class="text-red-500">*</span></label>
                            <select name="organization_id" id="organization_id" class="form-control" required>
                                @foreach($organizations as $org) <option value="{{ $org->id }}" {{ old('organization_id', $auction->organization_id) == $org->id ? 'selected' : '' }}>{{ $org->name }}</option> @endforeach
                            </select>
                        </div>
                        @else
                        <input type="hidden" name="organization_id" value="{{ $auction->organization_id }}">
                        @endif
                        <div>
                            <label for="tournament_id" class="form-label">Tournament <span class="text-red-500">*</span></label>
                            <select name="tournament_id" id="tournament_id" class="form-control" required>
                                @foreach($tournaments as $t) <option value="{{ $t->id }}" {{ old('tournament_id', $auction->tournament_id) == $t->id ? 'selected' : '' }}>{{ $t->name }}</option> @endforeach
                            </select>
                        </div>
                         <div>
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="scheduled" @selected(old('status', $auction->status) == 'scheduled')>Pending</option>
                                <option value="running" @selected(old('status', $auction->status) == 'running')>Live</option>
                                <option value="completed" @selected(old('status', $auction->status) == 'completed')>Completed</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Step 2: Financial Rules --}}
                <div x-show="step === 2" x-transition.opacity x-cloak class="space-y-6">
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="max_budget_per_team" class="form-label">Max Budget Per Team <span class="text-red-500">*</span></label>
                            <input type="number" name="max_budget_per_team" id="max_budget_per_team" value="{{ old('max_budget_per_team', $auction->max_budget_per_team) }}" class="form-control" required>
                        </div>
                        <div>
                            <label for="base_price" class="form-label">Default Player Base Price <span class="text-red-500">*</span></label>
                            <input type="number" name="base_price" id="base_price" value="{{ old('base_price', $auction->base_price) }}" class="form-control" required>
                        </div>
                    </div>
                </div>

                {{-- Step 3: Bid Increment Rules --}}
                <div x-show="step === 3" x-transition.opacity x-cloak>
                    <div>
                        <h3 class="font-semibold text-lg mb-2">Configure Bid Increments</h3>
                        <div class="space-y-3">
                            <template x-for="(rule, index) in rules" :key="index">
                                <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-900/50 p-3 rounded-md">
                                    <span class="text-gray-500">From</span><input type="number" :name="`bid_rules[${index}][from]`" x-model="rule.from" class="form-control w-1/4">
                                    <span class="text-gray-500">To</span><input type="number" :name="`bid_rules[${index}][to]`" x-model="rule.to" class="form-control w-1/4">
                                    <span class="text-gray-500">Increment</span><input type="number" :name="`bid_rules[${index}][increment]`" x-model="rule.increment" class="form-control w-1/4">
                                    <button type="button" @click="rules.splice(index, 1)" class="btn btn-danger btn-sm">&times;</button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="rules.push({ from: rules.length ? rules[rules.length-1].to : 0, to: '', increment: '' })" class="btn btn-secondary mt-4">+ Add Rule</button>
                    </div>
                </div>

                {{-- Step 4: Player Pool Management --}}
                <div x-show="step === 4" x-transition.opacity x-cloak>
                     <h3 class="font-semibold text-lg mb-2">Manage Auction Player Pool</h3>
                     <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        {{-- Players IN Auction --}}
                        <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg border">
                            <h4 class="font-semibold mb-2">Players in Pool (<span x-text="inAuction.length"></span>)</h4>
                            <div class="space-y-2 max-h-96 overflow-y-auto">
                                <template x-for="player in inAuction" :key="player.id">
                                    <div class="flex justify-between items-center p-2 bg-white dark:bg-gray-800 rounded shadow-sm">
                                        <span x-text="player.name"></span>
                                        <div class="flex items-center gap-2">
                                            <input type="number" :name="`player_base_prices[${player.id}]`" x-model="player.base_price" class="form-control form-control-sm w-28" placeholder="Base Price">
                                            <button type="button" @click="removeFromAuction(player)" class="text-red-500 hover:text-red-700">&times;</button>
                                            <input type="hidden" name="player_ids[]" :value="player.id">
                                        </div>
                                    </div>
                                </template>
                                <div x-show="inAuction.length === 0" class="text-center text-gray-500 py-4">Drag or add players from the right.</div>
                            </div>
                        </div>
                        {{-- Available Players --}}
                        <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg border">
                            <h4 class="font-semibold mb-2">Available Players</h4>
                            <input type="text" x-model="search" placeholder="Search available players..." class="form-control mb-2">
                            <div class="space-y-2 max-h-96 overflow-y-auto">
                                <template x-for="player in filteredAvailable" :key="player.id">
                                    <div class="flex justify-between items-center p-2 bg-white dark:bg-gray-800 rounded shadow-sm">
                                        <span x-text="player.name"></span>
                                        <button type="button" @click="addToAuction(player)" class="text-green-500 hover:text-green-700 font-bold">+</button>
                                    </div>
                                </template>
                                <div x-show="filteredAvailable.length === 0" class="text-center text-gray-500 py-4">No matching players found.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Form Footer with Navigation and Submit --}}
            <div class="p-6 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <div><a href="{{ route('admin.auctions.index') }}" class="btn btn-secondary">Cancel</a></div>
                <div class="flex items-center space-x-3">
                    <button type="button" x-show="step > 1" @click="step--" class="btn btn-secondary" x-cloak>Previous</button>
                    <button type="button" x-show="step < 4" @click="step++" class="btn btn-primary" x-cloak>Next</button>
                    <button type="submit" x-show="step === 4" class="btn btn-success" x-cloak>Update Auction & Pool</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection