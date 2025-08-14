@extends('backend.layouts.app')

@section('title', 'Create New Auction | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto lg:p-8">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Create New Auction</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Set up a new player auction by following the steps below.
                </p>
            </div>
        </div>

        {{-- Main Form Card with Alpine.js for Wizard Steps --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700"
            x-data="{
                step: 1,
                rules: [
                    { from: 0, to: 2000000, increment: 100000 },
                    { from: 2000000, to: 3000000, increment: 200000 },
                    { from: 3000000, to: 6000000, increment: 500000 },
                    { from: 6000000, to: 8000000, increment: 1000000 }
                ]
            }">

            <form action="{{ route('admin.auctions.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                {{-- START: DEBUGGING BLOCK - To See All Errors --}}
                {{-- ======================================================= --}}
                @if ($errors->any())
                    <div
                        class="p-4 mb-4 bg-red-100 dark:bg-red-900/50 border border-red-300 dark:border-red-700 rounded-lg text-red-800 dark:text-red-200">
                        <h3 class="font-bold">Validation Errors Found:</h3>
                        <ul class="mt-2 list-disc list-inside text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                {{-- Step Navigation --}}
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex space-x-4" aria-label="Tabs">
                        <a href="#" @click.prevent="step = 1"
                            :class="{
                                'border-blue-500 text-blue-600 dark:text-blue-400': step ===
                                    1,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !==
                                    1
                            }"
                            class="px-3 py-2 font-medium text-sm rounded-md border-b-2">1. Auction Details</a>
                        <a href="#" @click.prevent="step = 2"
                            :class="{
                                'border-blue-500 text-blue-600 dark:text-blue-400': step ===
                                    2,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !==
                                    2
                            }"
                            class="px-3 py-2 font-medium text-sm rounded-md border-b-2">2. Financial Rules</a>
                        <a href="#" @click.prevent="step = 3"
                            :class="{
                                'border-blue-500 text-blue-600 dark:text-blue-400': step ===
                                    3,
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': step !==
                                    3
                            }"
                            class="px-3 py-2 font-medium text-sm rounded-md border-b-2">3. Bid Increments</a>
                    </nav>
                </div>

                <div class="p-6 space-y-6">
                    {{-- Step 1: Auction Details --}}
                    {{-- Step 1: Auction Details --}}
                    <div x-show="step === 1" x-transition.opacity>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="form-label">Auction Name <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}"
                                    class="form-control" required>
                            </div>

                            @if (auth()->user()->hasRole('Superadmin'))
                                <div>
                                    <label for="organization_id" class="form-label">Organization <span
                                            class="text-red-500">*</span></label>
                                    <select name="organization_id" id="organization_id" class="form-control" required>
                                        <option value="">Select Organization</option>
                                        @foreach ($organizations as $org)
                                            <option value="{{ $org->id }}"
                                                {{ old('organization_id') == $org->id ? 'selected' : '' }}>
                                                {{ $org->name }}</option>
                                        @endforeach
                                    </select>
                                    {{-- **FIX**: Add the error display --}}
                                    @error('organization_id')
                                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            @else
                                <input type="hidden" name="organization_id" value="{{ auth()->user()->organization_id }}">
                            @endif

                            <div>
                                <label for="tournament_id" class="form-label">Tournament <span
                                        class="text-red-500">*</span></label>
                                <select name="tournament_id" id="tournament_id" class="form-control" required>
                                    <option value="">Select Tournament</option>
                                    @foreach ($tournaments as $tournament)
                                        <option value="{{ $tournament->id }}"
                                            {{ old('tournament_id') == $tournament->id ? 'selected' : '' }}>
                                            {{ $tournament->name }}</option>
                                    @endforeach
                                </select>
                                {{-- **FIX**: Add the error display --}}
                                @error('tournament_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="start_at" class="form-label">Start Date & Time <span
                                        class="text-red-500">*</span></label>
                                <input type="datetime-local" name="start_at" id="start_at" value="{{ old('start_at') }}"
                                    class="form-control" required>
                                @error('start_at')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Add End Date Input --}}
                            <div>
                                <label for="end_at" class="form-label">End Date & Time <span
                                        class="text-red-500">*</span></label>
                                <input type="datetime-local" name="end_at" id="end_at" value="{{ old('end_at') }}"
                                    class="form-control" required>
                                @error('end_at')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="status" class="form-label">Initial Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="pending" @selected(old('status', 'pending') == 'pending')>Pending</option>
                                    <option value="live" @selected(old('status') == 'live')>Live</option>
                                    <option value="completed" @selected(old('status') == 'completed')>Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Step 2: Financial Rules --}}
                    <div x-show="step === 2" x-transition.opacity x-cloak>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="max_budget_per_team" class="form-label">Max Budget Per Team <span
                                        class="text-red-500">*</span></label>
                                <input type="number" name="max_budget_per_team" id="max_budget_per_team"
                                    value="{{ old('max_budget_per_team', 10000000) }}" class="form-control" required>
                            </div>
                            <div>
                                <label for="base_price" class="form-label">Default Player Base Price <span
                                        class="text-red-500">*</span></label>
                                <input type="number" name="base_price" id="base_price"
                                    value="{{ old('base_price', 500000) }}" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    {{-- Step 3: Bid Increment Rules --}}
                    <div x-show="step === 3" x-transition.opacity x-cloak>
                        <div>
                            <h3 class="font-semibold text-lg mb-2 text-gray-800 dark:text-white">Configure Bid Increments
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Define how much the bid increases at
                                different price points.</p>

                            <div class="space-y-3">
                                <template x-for="(rule, index) in rules" :key="index">
                                    <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-900/50 p-3 rounded-md">
                                        <span class="text-gray-500">If price is between</span>
                                        <input type="number" :name="`bid_rules[${index}][from]`" x-model="rule.from"
                                            placeholder="From" class="form-control w-1/4">
                                        <span class="text-gray-500">and</span>
                                        <input type="number" :name="`bid_rules[${index}][to]`" x-model="rule.to"
                                            placeholder="To" class="form-control w-1/4">
                                        <span class="text-gray-500">, increment by</span>
                                        <input type="number" :name="`bid_rules[${index}][increment]`"
                                            x-model="rule.increment" placeholder="Increment" class="form-control w-1/4">
                                        <button type="button" @click="rules.splice(index, 1)"
                                            class="btn btn-danger btn-sm">&times;</button>
                                    </div>
                                </template>
                            </div>
                            <button type="button"
                                @click="rules.push({ from: rules[rules.length-1].to, to: '', increment: '' })"
                                class="btn btn-secondary mt-4">
                                + Add Rule
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Form Footer with Navigation and Submit --}}
                <div
                    class="p-6 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <a href="{{ route('admin.auctions.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                    <div class="flex items-center space-x-3">
                        {{-- Previous button remains a standard button --}}
                        <button type="button" x-show="step > 1" @click="step--" class="btn btn-secondary"
                            x-cloak>Previous</button>

                        {{-- Next button remains a standard button --}}
                        <button type="button" x-show="step < 3" @click="step++" class="btn btn-primary"
                            x-cloak>Next</button>

                        {{-- **THE FIX**: Ensure this is the ONLY submit button --}}
                        {{-- It is already correct, but this structure makes it clearer. --}}
                        <div x-show="step === 3" x-cloak>
                            <button type="submit" class="btn btn-success">
                                Create Auction
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

@endsection
