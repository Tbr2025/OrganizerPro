@extends('backend.layouts.app')

@section('title', 'Edit Auction | ' . $auction->name)

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8"
     x-data="auctionEditForm({{ json_encode($auction) }}, {{ json_encode($availablePlayers) }}, {{ json_encode($organizations) }}, {{ json_encode($tournaments) }})"
     x-init="init()">

    {{-- Toast Notification --}}
    <div x-show="toast.show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         :class="{
             'bg-green-500': toast.type === 'success',
             'bg-red-500': toast.type === 'error',
             'bg-blue-500': toast.type === 'info'
         }"
         class="fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-2xl text-white flex items-center gap-3"
         style="display: none;">
        <template x-if="toast.type === 'success'">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </template>
        <template x-if="toast.type === 'error'">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </template>
        <span x-text="toast.message"></span>
        <button @click="toast.show = false" class="ml-2 hover:bg-white/20 rounded p-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    {{-- Header Section --}}
    <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start gap-4 mb-6">
        <div class="flex items-start gap-4">
            <a href="{{ route('admin.auctions.index') }}"
               class="flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Edit Auction</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $auction->name }}</span>
                    <span class="mx-2">•</span>
                    <span>{{ $auction->tournament->name ?? 'No Tournament' }}</span>
                </p>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.auctions.show', $auction) }}"
               class="btn btn-secondary inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                View Pool
            </a>
            <a href="{{ route('admin.auction.organizer.panel', $auction) }}"
               class="btn btn-success inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Live Panel
            </a>
        </div>
    </div>

    {{-- Stats Overview Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-xs uppercase tracking-wide">Total Players</p>
                    <p class="text-2xl font-bold mt-1" x-text="inAuction.length">0</p>
                </div>
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-xs uppercase tracking-wide">Available</p>
                    <p class="text-2xl font-bold mt-1" x-text="available.length">0</p>
                </div>
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-xs uppercase tracking-wide">Budget/Team</p>
                    <p class="text-2xl font-bold mt-1" x-text="formatMoney(auctionData.max_budget_per_team)">0</p>
                </div>
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-4 text-white shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-orange-100 text-xs uppercase tracking-wide">Bid Rules</p>
                    <p class="text-2xl font-bold mt-1" x-text="rules.length">0</p>
                </div>
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Form Card --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">

        <form action="{{ route('admin.auctions.update', $auction) }}" method="POST" @submit.prevent="submitForm" x-ref="auctionFormElement">
            @csrf
            @method('PUT')

            {{-- Step Progress Bar --}}
            <div class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between max-w-3xl mx-auto">
                    <template x-for="(stepInfo, index) in steps" :key="index">
                        <div class="flex items-center" :class="index < steps.length - 1 ? 'flex-1' : ''">
                            {{-- Step Circle --}}
                            <button type="button"
                                    @click="step = index + 1"
                                    class="relative flex items-center justify-center w-12 h-12 rounded-full transition-all duration-300 focus:outline-none focus:ring-4 focus:ring-blue-300 dark:focus:ring-blue-800"
                                    :class="{
                                        'bg-blue-600 text-white shadow-lg shadow-blue-500/30': step === index + 1,
                                        'bg-green-500 text-white': step > index + 1,
                                        'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 hover:bg-gray-300 dark:hover:bg-gray-600': step < index + 1
                                    }">
                                <template x-if="step > index + 1">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </template>
                                <template x-if="step <= index + 1">
                                    <span x-html="stepInfo.icon"></span>
                                </template>
                            </button>

                            {{-- Step Label --}}
                            <div class="hidden sm:block ml-3" :class="index < steps.length - 1 ? 'mr-4' : ''">
                                <p class="text-xs font-medium uppercase tracking-wide"
                                   :class="step === index + 1 ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'"
                                   x-text="'Step ' + (index + 1)"></p>
                                <p class="text-sm font-semibold"
                                   :class="step === index + 1 ? 'text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-300'"
                                   x-text="stepInfo.title"></p>
                            </div>

                            {{-- Connector Line --}}
                            <template x-if="index < steps.length - 1">
                                <div class="hidden sm:block flex-1 h-1 mx-4 rounded-full transition-all duration-300"
                                     :class="step > index + 1 ? 'bg-green-500' : 'bg-gray-200 dark:bg-gray-700'"></div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Mobile Step Title --}}
                <div class="sm:hidden text-center mt-4">
                    <p class="text-lg font-semibold text-gray-900 dark:text-white" x-text="steps[step - 1].title"></p>
                </div>
            </div>

            {{-- Form Content --}}
            <div class="p-6">

                {{-- Step 1: Auction Details --}}
                <div x-show="step === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                    <div class="max-w-4xl mx-auto">
                        <div class="mb-6">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Basic Information</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Configure the auction name, tournament, and status.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Auction Name --}}
                            <div class="md:col-span-2">
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Auction Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="name" id="name" x-model="auctionData.name"
                                       class="form-control text-lg" required placeholder="e.g., Premier League Auction 2024">
                            </div>

                            {{-- Organization Select --}}
                            @if (auth()->user()->hasRole('Superadmin'))
                            <div>
                                <label for="organization_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Organization <span class="text-red-500">*</span>
                                </label>
                                <select name="organization_id" id="organization_id" class="form-control" required>
                                    @foreach ($organizations as $org)
                                        <option value="{{ $org->id }}" {{ old('organization_id', $auction->organization_id) == $org->id ? 'selected' : '' }}>
                                            {{ $org->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @else
                            <input type="hidden" name="organization_id" x-model="auctionData.organization_id">
                            @endif

                            {{-- Tournament Select --}}
                            <div>
                                <label for="tournament_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Tournament <span class="text-red-500">*</span>
                                </label>
                                <select name="tournament_id" id="tournament_id" class="form-control" required>
                                    @foreach ($tournaments as $t)
                                        <option value="{{ $t->id }}" {{ old('tournament_id', $auction->tournament_id) == $t->id ? 'selected' : '' }}>
                                            {{ $t->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Status --}}
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Status
                                </label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button type="button" @click="auctionData.status = 'scheduled'"
                                            class="px-4 py-3 rounded-lg border-2 transition-all text-center"
                                            :class="auctionData.status === 'scheduled' ? 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'">
                                        <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="text-xs font-medium">Scheduled</span>
                                    </button>
                                    <button type="button" @click="auctionData.status = 'running'"
                                            class="px-4 py-3 rounded-lg border-2 transition-all text-center"
                                            :class="auctionData.status === 'running' ? 'border-green-500 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'">
                                        <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="text-xs font-medium">Live</span>
                                    </button>
                                    <button type="button" @click="auctionData.status = 'completed'"
                                            class="px-4 py-3 rounded-lg border-2 transition-all text-center"
                                            :class="auctionData.status === 'completed' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'">
                                        <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <span class="text-xs font-medium">Completed</span>
                                    </button>
                                </div>
                                <input type="hidden" name="status" x-model="auctionData.status">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 2: Financial Rules --}}
                <div x-show="step === 2" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                    <div class="max-w-4xl mx-auto">
                        <div class="mb-6">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Financial Settings</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Set the budget limits and base prices for the auction.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            {{-- Max Budget Per Team --}}
                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-2xl p-6 border border-purple-200 dark:border-purple-800">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 dark:text-white">Team Budget</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Maximum budget per team</p>
                                    </div>
                                </div>
                                <input type="number" name="max_budget_per_team" id="max_budget_per_team"
                                       x-model.number="auctionData.max_budget_per_team"
                                       class="form-control text-2xl font-bold text-center mb-2" required>
                                <p class="text-center text-purple-600 dark:text-purple-400 font-semibold text-lg"
                                   x-text="formatMoney(auctionData.max_budget_per_team) + ' Points'"></p>
                            </div>

                            {{-- Default Base Price --}}
                            <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-2xl p-6 border border-green-200 dark:border-green-800">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 dark:text-white">Base Price</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Default starting price</p>
                                    </div>
                                </div>
                                <input type="number" name="base_price" id="base_price"
                                       x-model.number="auctionData.base_price"
                                       class="form-control text-2xl font-bold text-center mb-2" required>
                                <p class="text-center text-green-600 dark:text-green-400 font-semibold text-lg"
                                   x-text="formatMoney(auctionData.base_price) + ' Points'"></p>
                            </div>
                        </div>

                        {{-- Quick Presets --}}
                        <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-xl">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Quick Presets:</p>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="auctionData.max_budget_per_team = 10000000; auctionData.base_price = 100000"
                                        class="px-3 py-1.5 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 text-sm hover:border-blue-500 transition">
                                    10M / 100K
                                </button>
                                <button type="button" @click="auctionData.max_budget_per_team = 50000000; auctionData.base_price = 500000"
                                        class="px-3 py-1.5 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 text-sm hover:border-blue-500 transition">
                                    50M / 500K
                                </button>
                                <button type="button" @click="auctionData.max_budget_per_team = 100000000; auctionData.base_price = 1000000"
                                        class="px-3 py-1.5 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 text-sm hover:border-blue-500 transition">
                                    100M / 1M
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 3: Bid Increment Rules --}}
                <div x-show="step === 3" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                    <div class="max-w-4xl mx-auto">
                        <div class="mb-6">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Bid Increment Rules</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Configure how bid increments change based on the current price range.</p>
                        </div>

                        {{-- Rules List --}}
                        <div class="space-y-4">
                            <template x-for="(rule, index) in rules" :key="index">
                                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700 relative group">
                                    {{-- Remove Button --}}
                                    <button type="button" @click="removeRule(index)"
                                            class="absolute -top-2 -right-2 w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-lg hover:bg-red-600"
                                            x-show="rules.length > 1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>

                                    <div class="flex items-center gap-2 mb-4">
                                        <span class="w-8 h-8 bg-blue-500 text-white rounded-lg flex items-center justify-center text-sm font-bold"
                                              x-text="index + 1"></span>
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Rule</span>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        {{-- From --}}
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">From Price</label>
                                            <div class="relative">
                                                <input type="number" :name="`bid_rules[${index}][from]`"
                                                       x-model.number="rule.from"
                                                       class="form-control pr-16" required min="0">
                                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"
                                                      x-text="formatMoney(rule.from)"></span>
                                            </div>
                                        </div>

                                        {{-- To --}}
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">To Price</label>
                                            <div class="relative">
                                                <input type="number" :name="`bid_rules[${index}][to]`"
                                                       x-model.number="rule.to"
                                                       class="form-control pr-16" required min="0">
                                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"
                                                      x-text="formatMoney(rule.to)"></span>
                                            </div>
                                        </div>

                                        {{-- Increment --}}
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Increment</label>
                                            <div class="relative">
                                                <input type="number" :name="`bid_rules[${index}][increment]`"
                                                       x-model.number="rule.increment"
                                                       class="form-control pr-16" required min="0">
                                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"
                                                      x-text="'+' + formatMoney(rule.increment)"></span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Rule Preview --}}
                                    <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                        <p class="text-sm text-blue-700 dark:text-blue-300">
                                            <span class="font-medium">Preview:</span>
                                            When price is between <span class="font-bold" x-text="formatMoney(rule.from)"></span>
                                            and <span class="font-bold" x-text="formatMoney(rule.to)"></span>,
                                            each bid increases by <span class="font-bold text-green-600 dark:text-green-400" x-text="formatMoney(rule.increment)"></span>
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Add Rule Button --}}
                        <button type="button" @click="addRule()"
                                class="mt-4 w-full py-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl text-gray-500 dark:text-gray-400 hover:border-blue-500 hover:text-blue-500 transition-all flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add Another Rule
                        </button>
                    </div>
                </div>

                {{-- Step 4: Player Pool Management --}}
                <div x-show="step === 4" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                    <div class="mb-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Player Pool Management</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Add or remove players from the auction pool and set their base prices.</p>
                            </div>
                            {{-- Bulk Actions --}}
                            <div class="flex gap-2">
                                <button type="button" @click="addAllPlayers()"
                                        class="btn btn-sm bg-green-500 hover:bg-green-600 text-white"
                                        x-show="available.length > 0">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Add All (<span x-text="available.length"></span>)
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {{-- Players IN Auction Pool --}}
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-2xl p-5 border-2 border-blue-200 dark:border-blue-800">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-900 dark:text-white">In Pool</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><span x-text="inAuction.length"></span> players</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Search --}}
                            <div class="relative mb-4">
                                <input type="text" x-model="searchInPool" placeholder="Search players in pool..."
                                       class="form-control pl-10 bg-white dark:bg-gray-800">
                                <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>

                            {{-- Players List --}}
                            <div class="space-y-2 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                                <template x-for="player in inAuctionFiltered" :key="player.id">
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow group">
                                        <div class="flex items-center gap-3">
                                            {{-- Player Avatar --}}
                                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold text-lg flex-shrink-0"
                                                 x-text="player.name.charAt(0)"></div>

                                            {{-- Player Info --}}
                                            <div class="flex-1 min-w-0">
                                                <p class="font-semibold text-gray-900 dark:text-white truncate" x-text="player.name"></p>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <input type="number"
                                                           :name="`player_base_prices[${player.id}]`"
                                                           x-model.number="player.base_price"
                                                           class="form-control form-control-sm w-28 text-center font-medium"
                                                           placeholder="Base Price"
                                                           min="0"
                                                           @input.debounce.500ms="updatePlayerPrice(player)">
                                                    <span class="text-xs text-green-600 dark:text-green-400 font-medium"
                                                          x-text="formatMoney(player.base_price)"></span>
                                                </div>
                                            </div>

                                            {{-- Remove Button --}}
                                            <button type="button" @click="removeFromAuction(player)"
                                                    class="w-9 h-9 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-200 dark:hover:bg-red-900/50">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                {{-- Empty State --}}
                                <div x-show="inAuctionFiltered.length === 0" class="text-center py-12">
                                    <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    <p class="text-gray-500 dark:text-gray-400" x-text="searchInPool ? 'No players found matching your search.' : 'No players in pool yet.'"></p>
                                </div>
                            </div>
                        </div>

                        {{-- Available Players --}}
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-2xl p-5 border-2 border-green-200 dark:border-green-800">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-900 dark:text-white">Available</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><span x-text="available.length"></span> players</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Search --}}
                            <div class="relative mb-4">
                                <input type="text" x-model="search" placeholder="Search available players..."
                                       class="form-control pl-10 bg-white dark:bg-gray-800">
                                <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>

                            {{-- Players List --}}
                            <div class="space-y-2 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                                <template x-for="player in filteredAvailable" :key="player.id">
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow group cursor-pointer"
                                         @click="addToAuction(player)">
                                        <div class="flex items-center gap-3">
                                            {{-- Player Avatar --}}
                                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white font-bold text-lg flex-shrink-0"
                                                 x-text="player.name.charAt(0)"></div>

                                            {{-- Player Info --}}
                                            <div class="flex-1 min-w-0">
                                                <p class="font-semibold text-gray-900 dark:text-white truncate" x-text="player.name"></p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Click to add to pool</p>
                                            </div>

                                            {{-- Add Button --}}
                                            <div class="w-9 h-9 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 flex items-center justify-center group-hover:bg-green-500 group-hover:text-white transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                {{-- Empty State --}}
                                <div x-show="filteredAvailable.length === 0" class="text-center py-12">
                                    <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p class="text-gray-500 dark:text-gray-400" x-text="search ? 'No players found.' : 'All players have been added!'"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Form Footer --}}
            <div class="p-6 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    {{-- Cancel --}}
                    <a href="{{ route('admin.auctions.index') }}"
                       class="w-full sm:w-auto btn btn-secondary order-2 sm:order-1">
                        Cancel
                    </a>

                    {{-- Navigation & Submit --}}
                    <div class="flex items-center gap-3 w-full sm:w-auto order-1 sm:order-2">
                        <button type="button" @click="step--" x-show="step > 1"
                                class="flex-1 sm:flex-none btn btn-secondary" x-cloak>
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Previous
                        </button>

                        <button type="button" @click="step++" x-show="step < 4"
                                class="flex-1 sm:flex-none btn btn-primary" x-cloak>
                            Next
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <button type="button" @click="submitForm()" x-show="step === 4"
                                class="flex-1 sm:flex-none btn btn-success px-8"
                                :disabled="isSubmitting" x-cloak>
                            <template x-if="!isSubmitting">
                                <span class="flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Save Changes
                                </span>
                            </template>
                            <template x-if="isSubmitting">
                                <span class="flex items-center">
                                    <svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Saving...
                                </span>
                            </template>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #475569;
    }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #64748b;
    }
</style>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('auctionEditForm', (auctionData, availablePlayersData, organizationsData, tournamentsData) => ({
        // State
        step: 1,
        auctionData: { ...auctionData },
        rules: [],
        inAuction: [],
        available: [],
        search: '',
        searchInPool: '',
        isSubmitting: false,
        toast: { show: false, message: '', type: 'success' },

        // Step configuration
        steps: [
            { title: 'Details', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>' },
            { title: 'Financials', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' },
            { title: 'Bid Rules', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>' },
            { title: 'Players', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>' }
        ],

        init() {
            // Initialize bid rules
            this.rules = auctionData.bid_rules && auctionData.bid_rules.length > 0
                ? auctionData.bid_rules.map(rule => ({ ...rule }))
                : [{ from: 0, to: '', increment: '' }];

            // Initialize players in auction
            this.inAuction = auctionData.auction_players.map(ap => ({
                id: ap.player.id,
                name: ap.player.name,
                base_price: ap.base_price
            }));

            // Initialize available players
            this.available = availablePlayersData.map(p => ({
                id: p.id,
                name: p.name
            }));

            // Filter out players already in pool
            this.available = this.available.filter(p => !this.inAuction.some(ip => ip.id === p.id));

            // Sort lists
            this.available.sort((a, b) => a.name.localeCompare(b.name));
            this.inAuction.sort((a, b) => a.name.localeCompare(b.name));
        },

        // Computed: filtered players in auction
        get inAuctionFiltered() {
            if (!this.searchInPool) return this.inAuction;
            return this.inAuction.filter(p => p.name.toLowerCase().includes(this.searchInPool.toLowerCase()));
        },

        // Computed: filtered available players
        get filteredAvailable() {
            if (!this.search) return this.available;
            return this.available.filter(p => p.name.toLowerCase().includes(this.search.toLowerCase()));
        },

        // Format money
        formatMoney(value) {
            const num = Number(value) || 0;
            if (num >= 1000000) return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
            if (num >= 1000) return (num / 1000).toFixed(0) + 'K';
            return num.toString();
        },

        // Show toast notification
        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },

        // Add bid rule
        addRule() {
            const lastTo = this.rules.length > 0 ? this.rules[this.rules.length - 1].to : 0;
            this.rules.push({ from: lastTo, to: '', increment: '' });
        },

        // Remove bid rule
        removeRule(index) {
            if (this.rules.length > 1) {
                this.rules.splice(index, 1);
            }
        },

        // Add player to auction
        async addToAuction(player) {
            const defaultBasePrice = this.auctionData.base_price;

            try {
                const response = await fetch(`/admin/auctions/{{ $auction->id }}/players/${player.id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ base_price: defaultBasePrice })
                });

                const data = await response.json();

                if (!response.ok) {
                    this.showToast('Error: ' + (data.error || response.statusText), 'error');
                    return;
                }

                // Update UI
                this.inAuction.push({
                    id: player.id,
                    name: player.name,
                    base_price: data.player.base_price
                });
                this.inAuction.sort((a, b) => a.name.localeCompare(b.name));
                this.available = this.available.filter(p => p.id !== player.id);
                this.search = '';
                this.showToast(`${player.name} added to pool`, 'success');
            } catch (error) {
                this.showToast('Network error', 'error');
            }
        },

        // Remove player from auction
        async removeFromAuction(player) {
            if (!confirm(`Remove ${player.name} from the pool?`)) return;

            try {
                const response = await fetch(`/admin/auctions/{{ $auction->id }}/players/${player.id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const data = await response.json();

                if (!response.ok) {
                    this.showToast('Error: ' + (data.error || response.statusText), 'error');
                    return;
                }

                // Update UI
                this.available.push({ id: player.id, name: player.name });
                this.available.sort((a, b) => a.name.localeCompare(b.name));
                this.inAuction = this.inAuction.filter(p => p.id !== player.id);
                this.searchInPool = '';
                this.showToast(`${player.name} removed`, 'success');
            } catch (error) {
                this.showToast('Network error', 'error');
            }
        },

        // Update player price
        async updatePlayerPrice(player) {
            try {
                const response = await fetch(`/admin/auctions/{{ $auction->id }}/players/${player.id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ base_price: player.base_price })
                });

                const data = await response.json();

                if (!response.ok) {
                    this.showToast(`Error updating price for ${player.name}`, 'error');
                    return;
                }

                this.showToast(`Price updated for ${player.name}`, 'success');
            } catch (error) {
                this.showToast('Network error', 'error');
            }
        },

        // Add all available players
        async addAllPlayers() {
            if (!confirm(`Add all ${this.available.length} players to the pool?`)) return;

            const playersToAdd = [...this.available];
            for (const player of playersToAdd) {
                await this.addToAuction(player);
            }
        },

        // Prepare form for submission
        prepareFormSubmit() {
            document.querySelectorAll('input[name="player_ids[]"]').forEach(el => el.remove());
            document.querySelectorAll('input[name^="player_base_prices["]').forEach(el => el.remove());

            this.inAuction.forEach(player => {
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'player_ids[]';
                idInput.value = player.id;
                this.$refs.auctionFormElement.appendChild(idInput);

                const priceInput = document.createElement('input');
                priceInput.type = 'hidden';
                priceInput.name = `player_base_prices[${player.id}]`;
                priceInput.value = player.base_price;
                this.$refs.auctionFormElement.appendChild(priceInput);
            });
        },

        // Submit form
        submitForm() {
            this.isSubmitting = true;
            this.prepareFormSubmit();
            this.$refs.auctionFormElement.submit();
        }
    }));
});
</script>
@endpush
