@extends('backend.layouts.app')

@section('title', 'Edit Auction | ' . $auction->name)

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8">
    <x-breadcrumbs :breadcrumbs="['title' => 'Edit Auction', 'items' => [['label' => 'Auctions', 'url' => route('admin.auctions.index')]]]" />
</div>
<div class="p-4 mx-auto max-w-7xl md:p-6 lg:p-8"
     x-data="auctionEditForm({{ json_encode($auction) }}, {{ json_encode($availablePlayers) }}, {{ json_encode($existingPools) }}, {{ json_encode($unpooled) }})"
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
                    <p class="text-2xl font-bold mt-1" x-text="totalPooledPlayers">0</p>
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
                    <p class="text-2xl font-bold mt-1" x-text="filteredAvailable.length">0</p>
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

        <form action="{{ route('admin.auctions.update', $auction) }}" method="POST" enctype="multipart/form-data" @submit.prevent="submitForm" x-ref="auctionFormElement">
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

                            {{-- Bidding Mode. Edit reads auctionData, which is the spread of
                                 json_encode($auction), so the column arrives for free. --}}
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bidding Mode <span class="text-red-500">*</span></label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                    Who enters the bids. This is a decision about the whole auction, not a phase.
                                </p>
                                <div class="grid grid-cols-2 gap-4">
                                    <button type="button" @click="auctionData.open_bid_mode = 'online'"
                                            class="p-4 rounded-lg border-2 transition-all text-left"
                                            :class="auctionData.open_bid_mode === 'online' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                                 :class="auctionData.open_bid_mode === 'online' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500'">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                            </div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">Online</h4>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Team managers bid from their own dashboards, wherever they are.</p>
                                    </button>
                                    <button type="button" @click="auctionData.open_bid_mode = 'offline'"
                                            class="p-4 rounded-lg border-2 transition-all text-left"
                                            :class="auctionData.open_bid_mode === 'offline' ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                                 :class="auctionData.open_bid_mode === 'offline' ? 'bg-orange-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500'">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                            </div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">Offline</h4>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Everyone is in the room. Only the admin and organizer enter bids &mdash; they tap a team's logo and the price rises by the increment.</p>
                                    </button>
                                </div>
                                <input type="hidden" name="open_bid_mode" x-model="auctionData.open_bid_mode">
                            </div>

                            {{-- Bid Type --}}
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bid Type <span class="text-red-500">*</span></label>
                                <div class="grid grid-cols-2 gap-4">
                                    <button type="button" @click="auctionData.bid_type = 'open'"
                                            class="p-4 rounded-lg border-2 transition-all text-left"
                                            :class="auctionData.bid_type === 'open' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                                 :class="auctionData.bid_type === 'open' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500'">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            </div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">Open Bid</h4>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Real-time bidding. All teams see each other's bids live.</p>
                                    </button>
                                    <button type="button" @click="auctionData.bid_type = 'closed'"
                                            class="p-4 rounded-lg border-2 transition-all text-left"
                                            :class="auctionData.bid_type === 'closed' ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                                 :class="auctionData.bid_type === 'closed' ? 'bg-purple-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500'">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path></svg>
                                            </div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">Closed Bid</h4>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Sealed bidding. Only admin sees all bids and decides the winner.</p>
                                    </button>
                                </div>
                                <input type="hidden" name="bid_type" x-model="auctionData.bid_type">
                            </div>

                            {{-- Phase Transition Thresholds (only for Open Bid) --}}
                            <div class="md:col-span-2" x-show="auctionData.bid_type === 'open'" x-transition x-cloak>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Auto Phase Transitions</label>
                                {{-- These are TWO independent settings, not three stages of one.
                                     "Open → Closed → Offline" was wrong and it misled: offline
                                     is not a phase that comes after sealed bidding. Who enters
                                     the bids, and whether bidding is open or sealed, are
                                     separate axes — the sealed round happens either way. --}}
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Two separate rules, both driven by the price.</p>
                                <ul class="text-xs text-gray-500 dark:text-gray-400 mb-3 space-y-1 pl-4 list-disc">
                                    <li><strong>Who enters the bids</strong> — teams from their own screens (online), or the organizer on their behalf in the room (offline).</li>
                                    <li><strong>How bidding works</strong> — open, where every bid is visible as it happens, or closed, where each team submits one private amount and the highest wins.</li>
                                </ul>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                                    The sealed round runs in <strong>both</strong> modes. Online, the teams type their own amount; offline, the organizer enters each team's amount for them on the control panel. Either way the highest submitted amount takes the player.
                                </p>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label for="online_bid_limit_from" class="form-label text-xs">Online Bid Starts From</label>
                                        <div class="relative">
                                            <input type="number" step="any" min="0" id="online_bid_limit_from"
                                                   :value="toM(auctionData.online_bid_limit_from)"
                                                   @input="auctionData.online_bid_limit_from = fromM($event.target.value)"
                                                   class="form-control pr-9" placeholder="e.g. 0.1">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                        </div>
                                        <input type="hidden" name="online_bid_limit_from" :value="auctionData.online_bid_limit_from">
                                        <p class="text-xs text-gray-400 mt-1">Informational only — it changes nothing on its own.</p>
                                    </div>
                                    <div>
                                        <label for="closed_bid_starts_at" class="form-label text-xs">Closed Bid Starts At</label>
                                        <div class="relative">
                                            <input type="number" step="any" min="0" id="closed_bid_starts_at"
                                                   :value="toM(auctionData.closed_bid_starts_at)"
                                                   @input="auctionData.closed_bid_starts_at = fromM($event.target.value)"
                                                   class="form-control pr-9" placeholder="e.g. 0.5">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                        </div>
                                        <input type="hidden" name="closed_bid_starts_at" :value="auctionData.closed_bid_starts_at">
                                        <p class="text-xs text-gray-400 mt-1">Once a bid reaches this, open bidding stops and the sealed round begins — in online <em>and</em> offline mode.</p>
                                    </div>
                                    <div x-show="auctionData.open_bid_mode === 'online'" x-cloak>
                                        <label for="online_bid_limit_to" class="form-label text-xs">Organizer Enters Bids From</label>
                                        <div class="relative">
                                            <input type="number" step="any" min="0" id="online_bid_limit_to"
                                                   :value="toM(auctionData.online_bid_limit_to)"
                                                   @input="auctionData.online_bid_limit_to = fromM($event.target.value)"
                                                   class="form-control pr-9" placeholder="e.g. 1">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                        </div>
                                        <input type="hidden" name="online_bid_limit_to" :value="auctionData.online_bid_limit_to">
                                        <p class="text-xs text-gray-400 mt-1">Above this price the organizer enters bids for the teams instead of the teams bidding themselves. It does not skip or replace the sealed round.</p>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Leave a field empty to switch that rule off. The organizer can also override either one by hand during the auction, and an override they make is not undone by the next player.</p>
                            </div>

                            {{-- Sealed round rules. Only meaningful once a closed-bid
                                 threshold exists, so the card follows it. --}}
                            <div class="mt-6 p-5 bg-indigo-50 dark:bg-indigo-900/10 rounded-2xl border border-indigo-200 dark:border-indigo-800/60">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Sealed Round</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-4">
                                    Once the price reaches the closed-bid threshold, teams submit a private amount.
                                    The highest wins; a tie goes to a re-bid and then to a drawn lot.
                                </p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="closed_bid_step" class="form-label text-xs">Bid Step</label>
                                        <div class="relative">
                                            <input type="number" step="any" min="0" id="closed_bid_step"
                                                   :value="toM(auctionData.closed_bid_step)"
                                                   @input="auctionData.closed_bid_step = fromM($event.target.value)"
                                                   class="form-control pr-9" placeholder="0.1">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                        </div>
                                        <input type="hidden" name="closed_bid_step" :value="auctionData.closed_bid_step">
                                        <p class="text-xs text-gray-400 mt-1">
                                            Sealed amounts must be exact multiples of this. Anything else is refused, not rounded. Blank uses 0.1M.
                                        </p>
                                    </div>
                                    <div>
                                        <label for="closed_bid_max_pct_of_budget" class="form-label text-xs">Max Spend Per Player</label>
                                        <div class="relative">
                                            <input type="number" step="1" min="1" max="100" id="closed_bid_max_pct_of_budget"
                                                   x-model.number="auctionData.closed_bid_max_pct_of_budget"
                                                   class="form-control pr-9" placeholder="70">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">%</span>
                                        </div>
                                        <input type="hidden" name="closed_bid_max_pct_of_budget" :value="auctionData.closed_bid_max_pct_of_budget">
                                        <p class="text-xs text-gray-400 mt-1">
                                            Share of a team's <strong>total</strong> budget one player may cost. Blank uses 70%.
                                        </p>
                                    </div>
                                    <div>
                                        <label for="closed_bid_max_rebid_rounds" class="form-label text-xs">Re-bid Rounds On A Tie</label>
                                        <input type="number" name="closed_bid_max_rebid_rounds" id="closed_bid_max_rebid_rounds" min="0" max="5"
                                               x-model.number="auctionData.closed_bid_max_rebid_rounds"
                                               class="form-control" placeholder="2">
                                        <p class="text-xs text-gray-400 mt-1">
                                            Tied teams bid again this many times, then a lot is drawn. 0 goes straight to the lot.
                                        </p>
                                    </div>
                                    <div>
                                        <label for="closed_bid_timer_seconds" class="form-label text-xs">Sealed Round Timer</label>
                                        <div class="relative">
                                            <input type="number" name="closed_bid_timer_seconds" id="closed_bid_timer_seconds" min="5" max="600"
                                                   x-model.number="auctionData.closed_bid_timer_seconds"
                                                   class="form-control pr-9" :placeholder="auctionData.bid_timer_seconds || 30">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">s</span>
                                        </div>
                                        <p class="text-xs text-gray-400 mt-1">Blank uses the ordinary bid timer.</p>
                                    </div>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <label class="flex items-start gap-2 cursor-pointer">
                                        <input type="hidden" name="closed_bid_requires_acceptance" value="0">
                                        <input type="checkbox" name="closed_bid_requires_acceptance" value="1"
                                               x-model="auctionData.closed_bid_requires_acceptance"
                                               class="mt-0.5 rounded border-gray-300 text-indigo-600">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            Teams must accept the purse conditions before bidding
                                            <span class="block text-xs text-gray-400">
                                                They are shown their purse, the places still to fill, the amount held back and their maximum bid.
                                            </span>
                                        </span>
                                    </label>

                                    <div>
                                        <label for="closed_bid_tie_breaker" class="form-label text-xs">After The Last Re-bid</label>
                                        <select name="closed_bid_tie_breaker" id="closed_bid_tie_breaker"
                                                x-model="auctionData.closed_bid_tie_breaker" class="form-control">
                                            <option value="lot">Draw a lot</option>
                                            <option value="manual">Organizer decides</option>
                                        </select>
                                        <p class="text-xs text-gray-400 mt-1">
                                            A drawn lot records its seed, so the result can be recomputed and checked afterwards.
                                        </p>
                                    </div>
                                </div>

                                {{-- The three ways this configuration can be made unusable are
                                     refused on save; this warns before that happens. --}}
                                <template x-if="sealedCapBelowThreshold">
                                    <p class="mt-3 text-xs px-3 py-2 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300">
                                        A sealed round opening at <span class="font-semibold" x-text="formatMoney(auctionData.closed_bid_starts_at)"></span>
                                        is above the per-player cap of
                                        <span class="font-semibold" x-text="formatMoney(sealedPerPlayerCap)"></span> —
                                        no team could bid the opening amount.
                                    </p>
                                </template>
                            </div>

                            {{-- Timer Settings --}}
                            <div>
                                <label for="bid_timer_seconds" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Bid Timer (seconds) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" name="bid_timer_seconds" id="bid_timer_seconds"
                                       x-model.number="auctionData.bid_timer_seconds" class="form-control" min="5" max="300" required>
                                <p class="text-xs text-gray-500 mt-1">Countdown per player during auction.</p>
                            </div>

                            <div x-show="auctionData.bid_type === 'open'" x-transition>
                                <label for="bid_timer_reset_seconds" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Timer Reset on New Bid (seconds)
                                </label>
                                <input type="number" name="bid_timer_reset_seconds" id="bid_timer_reset_seconds"
                                       x-model.number="auctionData.bid_timer_reset_seconds" class="form-control" min="5" max="300">
                                <p class="text-xs text-gray-500 mt-1">Timer resets to this value when a new bid is placed.</p>
                            </div>

                            {{-- What happens at zero. The countdown is enforced by the
                                 server, so this decides a real outcome. --}}
                            <div>
                                <label for="timer_expiry_action" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    When the Timer Runs Out
                                </label>
                                <select name="timer_expiry_action" id="timer_expiry_action"
                                        x-model="auctionData.timer_expiry_action" class="form-control">
                                    <option value="manual">Lock bidding — organizer presses SELL</option>
                                    <option value="auto_sell">Sell automatically to the highest bidder</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    <span x-show="auctionData.timer_expiry_action === 'auto_sell'">
                                        The player is awarded the moment time runs out (or marked unsold if there were no bids).
                                    </span>
                                    <span x-show="auctionData.timer_expiry_action !== 'auto_sell'">
                                        Bidding closes at zero, but you stay in control of the hammer.
                                    </span>
                                </p>
                            </div>

                            <div>
                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <input type="hidden" name="timer_enabled" value="0">
                                    <input type="checkbox" name="timer_enabled" value="1"
                                           x-model="auctionData.timer_enabled"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    Timer enabled
                                </label>
                                <p class="text-xs text-gray-500 mt-1">
                                    Required while bidding is online. Can be switched off during an offline
                                    (organizer-called) auction, and toggled live from the control panel.
                                </p>
                            </div>

                            {{-- Closing calls: "going once, going twice, sold". --}}
                            <div class="md:col-span-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <input type="hidden" name="final_call_enabled" value="0">
                                    <input type="checkbox" name="final_call_enabled" value="1"
                                           x-model="auctionData.final_call_enabled"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    Announce closing calls
                                </label>
                                <p class="text-xs text-gray-500 mt-1">
                                    In the closing seconds every screen escalates
                                    <strong>First call → Second call → Final call</strong>, then the timer action above
                                    resolves the player.
                                </p>

                                <div x-show="auctionData.final_call_enabled" x-transition class="mt-3 max-w-xs">
                                    <label for="final_call_interval_seconds" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Gap Between Calls (seconds)
                                    </label>
                                    <input type="number" name="final_call_interval_seconds" id="final_call_interval_seconds"
                                           x-model.number="auctionData.final_call_interval_seconds"
                                           class="form-control" min="1" max="30">
                                    <p class="text-xs text-gray-500 mt-1">
                                        Three calls at this spacing, so
                                        <span class="font-semibold" x-text="(auctionData.final_call_interval_seconds || 3) * 3"></span>s
                                        covers the closing window — calls at
                                        <span class="font-semibold" x-text="[(auctionData.final_call_interval_seconds || 3) * 3, (auctionData.final_call_interval_seconds || 3) * 2, (auctionData.final_call_interval_seconds || 3)].join('s, ') + 's'"></span>
                                        remaining.
                                    </p>
                                </div>
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
                                <div class="relative mb-2">
                                    <input type="number" step="any" min="0" id="max_budget_per_team"
                                           :value="toM(auctionData.max_budget_per_team)"
                                           @input="auctionData.max_budget_per_team = fromM($event.target.value)"
                                           class="form-control text-2xl font-bold text-center pr-10" placeholder="10" required>
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-lg font-bold text-gray-400 pointer-events-none">M</span>
                                </div>
                                <input type="hidden" name="max_budget_per_team" :value="auctionData.max_budget_per_team">
                                <p class="text-center text-purple-600 dark:text-purple-400 font-semibold text-lg"
                                   x-text="formatMoney(auctionData.max_budget_per_team)"></p>
                                <p class="text-center text-[11px] text-gray-500 dark:text-gray-400 font-mono"
                                   x-text="rawLabel(auctionData.max_budget_per_team)"></p>
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
                                <div class="relative mb-2">
                                    <input type="number" step="any" min="0" id="base_price"
                                           :value="toM(auctionData.base_price)"
                                           @input="auctionData.base_price = fromM($event.target.value)"
                                           class="form-control text-2xl font-bold text-center pr-10" placeholder="0.1" required>
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-lg font-bold text-gray-400 pointer-events-none">M</span>
                                </div>
                                <input type="hidden" name="base_price" :value="auctionData.base_price">
                                <p class="text-center text-green-600 dark:text-green-400 font-semibold text-lg"
                                   x-text="formatMoney(auctionData.base_price)"></p>
                                <p class="text-center text-[11px] text-gray-500 dark:text-gray-400 font-mono"
                                   x-text="rawLabel(auctionData.base_price)"></p>
                            </div>
                        </div>

                        {{-- Player email. Auction mail used to be sent inline on every sale,
                             so the room waited on the mail server and a rehearsal emailed
                             real players. --}}
                        <div class="mt-6 p-5 bg-emerald-50 dark:bg-emerald-900/10 rounded-2xl border border-emerald-200 dark:border-emerald-800/60">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Player Emails &amp; Notifications</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-4">
                                Sold, unsold and welcome-to-team emails for this auction.
                            </p>

                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <input type="hidden" name="notifications_enabled" value="0">
                                <input type="checkbox" name="notifications_enabled" value="1"
                                       x-model="auctionData.notifications_enabled"
                                       class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                Send player emails for this auction
                            </label>
                            <p class="text-xs text-gray-500 mt-1 ml-6">Off means no player mail at all — nothing is queued.</p>

                            <div x-show="auctionData.notifications_enabled" x-transition class="mt-4 ml-6 space-y-4">
                                <div class="max-w-md">
                                    <label for="email_dispatch" class="form-label text-xs">When to send</label>
                                    <select name="email_dispatch" id="email_dispatch"
                                            x-model="auctionData.email_dispatch" class="form-control">
                                        <option value="deferred">After the auction ends — queued (recommended)</option>
                                        <option value="immediate">Immediately, as each player is sold</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <span x-show="auctionData.email_dispatch === 'deferred'">
                                            Emails are held while the auction runs and all go out together when you press
                                            End — including the welcome-to-team cards, which render a poster each.
                                        </span>
                                        <span x-show="auctionData.email_dispatch === 'immediate'">
                                            Each sale sends straight away. Simpler, but the panel waits on the mail server
                                            every time the hammer falls.
                                        </span>
                                    </p>
                                </div>

                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <input type="hidden" name="email_test_mode" value="0">
                                    <input type="checkbox" name="email_test_mode" value="1"
                                           x-model="auctionData.email_test_mode"
                                           class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                    Test mode — record emails but never send them
                                </label>
                                <p class="text-xs text-gray-500 ml-6">
                                    Use for a rehearsal. You still get a list of everything a real run would have sent.
                                </p>

                                <p x-show="auctionData.email_test_mode"
                                   class="text-xs px-3 py-2 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200">
                                    Test mode is on — no player will receive anything from this auction.
                                </p>
                            </div>
                        </div>

                        {{-- What the money is called. Amounts always read on the K / M / B
                             ladder; this only decides the label beside the figure. --}}
                        <div class="mt-6 p-5 bg-blue-50 dark:bg-blue-900/10 rounded-2xl border border-blue-200 dark:border-blue-800/60">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Amount Unit</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-4">
                                What amounts are called across the control panel, bidding page and the audience display.
                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="amount_unit" class="form-label text-xs">Unit</label>
                                    <select name="amount_unit" id="amount_unit" x-model="auctionData.amount_unit" class="form-control">
                                        @foreach(\App\Models\Auction::amountUnitOptions() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div x-show="auctionData.amount_unit === 'custom'" x-transition>
                                    <label for="amount_unit_label" class="form-label text-xs">Custom Label</label>
                                    <input type="text" name="amount_unit_label" id="amount_unit_label" maxlength="30"
                                           x-model="auctionData.amount_unit_label"
                                           class="form-control" placeholder="e.g. Credits">
                                </div>
                            </div>

                            {{-- Live sample, so the choice is unambiguous before saving. --}}
                            <p class="mt-3 text-xs text-gray-600 dark:text-gray-300">
                                Amounts will read as
                                <span class="font-bold font-mono px-2 py-0.5 rounded bg-white dark:bg-gray-800"
                                      x-text="unitSample(10000000)"></span>
                                and
                                <span class="font-bold font-mono px-2 py-0.5 rounded bg-white dark:bg-gray-800"
                                      x-text="unitSample(500000)"></span>
                            </p>
                        </div>

                        {{-- Squad reserve: a team must hold back enough purse to fill the
                             places it still has to fill, so it cannot spend everything
                             early and end up unable to field a legal side. --}}
                        <div class="mt-6 p-5 bg-amber-50 dark:bg-amber-900/10 rounded-2xl border border-amber-200 dark:border-amber-800/60">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Squad Reserve Rule</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-4">
                                Teams must keep back enough to buy the places they still have to fill. A bid is
                                refused if it would leave them unable to complete a legal squad.
                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="min_squad_size" class="form-label text-xs">Minimum Squad Size</label>
                                    <input type="number" name="min_squad_size" id="min_squad_size" min="1" max="50"
                                           x-model.number="auctionData.min_squad_size"
                                           class="form-control" placeholder="11">
                                    <p class="text-xs text-gray-400 mt-1">Players each team must end up with.</p>
                                </div>
                                <div>
                                    <label for="min_price_per_player" class="form-label text-xs">Reserve Per Remaining Place</label>
                                    <div class="relative">
                                        <input type="number" step="any" min="0" id="min_price_per_player"
                                               :value="toM(auctionData.min_price_per_player)"
                                               @input="auctionData.min_price_per_player = fromM($event.target.value)"
                                               class="form-control pr-9" placeholder="e.g. 1">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                    </div>
                                    <input type="hidden" name="min_price_per_player" :value="auctionData.min_price_per_player">
                                    <p class="text-xs text-gray-400 mt-1">Leave blank to use the base price above.</p>
                                </div>
                                <div>
                                    <label for="max_squad_size" class="form-label text-xs">Maximum Squad Size</label>
                                    <input type="number" name="max_squad_size" id="max_squad_size" min="1" max="50"
                                           x-model.number="auctionData.max_squad_size"
                                           class="form-control" placeholder="No maximum">
                                    <p class="text-xs text-gray-400 mt-1">
                                        Shown on the live screens. Blank means no ceiling — it never blocks a bid.
                                    </p>
                                </div>
                            </div>

                            {{-- Live check: the rule is only satisfiable when a full squad
                                 fits inside the purse, and the server rejects it otherwise. --}}
                            <template x-if="reserveTotal > 0">
                                <p class="mt-3 text-xs px-3 py-2 rounded-lg"
                                   :class="reserveExceedsBudget
                                        ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300'">
                                    A squad of <span class="font-semibold" x-text="auctionData.min_squad_size || 11"></span>
                                    at <span class="font-semibold" x-text="formatMoney(reservePerPlace)"></span> each needs
                                    <span class="font-semibold" x-text="formatMoney(reserveTotal)"></span>.
                                    <template x-if="reserveExceedsBudget">
                                        <span>That is more than the <span class="font-semibold" x-text="formatMoney(auctionData.max_budget_per_team)"></span>
                                        team budget, so no player could ever be bought — raise the budget or lower these figures.</span>
                                    </template>
                                    <template x-if="!reserveExceedsBudget">
                                        <span>Opening bid cap:
                                        <span class="font-semibold" x-text="formatMoney(Math.max(0, (Number(auctionData.max_budget_per_team) || 0) - reserveTotal + reservePerPlace))"></span>.</span>
                                    </template>
                                </p>
                            </template>
                        </div>

                        {{-- Retention defaults: a blank retention price used to be stored as
                             0, so a retained player cost their team nothing. --}}
                        <div class="mt-6 p-5 bg-purple-50 dark:bg-purple-900/10 rounded-2xl border border-purple-200 dark:border-purple-800/60">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Retained Players</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-4">
                                Retentions are charged against a team's budget before the auction starts.
                            </p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="default_retained_value" class="form-label text-xs">Default Retention Price</label>
                                    <div class="relative">
                                        <input type="number" step="any" min="0" id="default_retained_value"
                                               :value="toM(auctionData.default_retained_value)"
                                               @input="auctionData.default_retained_value = fromM($event.target.value)"
                                               class="form-control pr-9" placeholder="5">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                    </div>
                                    <input type="hidden" name="default_retained_value" :value="auctionData.default_retained_value">
                                    <p class="text-xs text-gray-400 mt-1">
                                        Used when no price is entered for a retained player. Blank uses 5M; enter 0 to make retentions free.
                                    </p>
                                </div>
                                <div>
                                    <label for="expected_retained_per_team" class="form-label text-xs">Expected Retentions Per Team</label>
                                    <input type="number" name="expected_retained_per_team" id="expected_retained_per_team" min="0" max="50"
                                           x-model.number="auctionData.expected_retained_per_team"
                                           class="form-control" placeholder="4">
                                    <p class="text-xs text-gray-400 mt-1">
                                        Flags teams whose count differs. Advisory only — a team may retain more.
                                    </p>
                                </div>
                            </div>

                            {{-- Soft check only: retentions are per-player priced, so committing
                                 more than the budget on paper is a warning, never a refusal. --}}
                            <template x-if="retentionCommitment > 0">
                                <p class="mt-3 text-xs px-3 py-2 rounded-lg"
                                   :class="retentionExceedsBudget
                                        ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300'">
                                    <span x-text="auctionData.expected_retained_per_team || 4"></span> retentions at
                                    <span class="font-semibold" x-text="formatMoney(retentionPrice)"></span> each commits
                                    <span class="font-semibold" x-text="formatMoney(retentionCommitment)"></span> of the
                                    <span class="font-semibold" x-text="formatMoney(auctionData.max_budget_per_team)"></span> team budget
                                    before a single bid.
                                    <template x-if="retentionExceedsBudget">
                                        <span class="font-semibold">That is more than the budget — price retentions individually or raise it.</span>
                                    </template>
                                </p>
                            </template>
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

                        {{-- Per-team budgets (optional overrides; blank = uniform cap) --}}
                        @if(($budgetTeams ?? collect())->isNotEmpty())
                        <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-xl">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Per-team budgets</p>
                                <span class="text-xs text-gray-400">Blank = uniform cap (<span x-text="formatMoney(auctionData.max_budget_per_team)"></span>)</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Override the budget for specific teams in this tournament.</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($budgetTeams as $team)
                                    {{-- Entered in millions like every other money field on this
                                         page. The raw value rides in a hidden input, and blank must
                                         stay blank: an empty string clears the override, whereas a 0
                                         would be honoured as "this team has no money at all". --}}
                                    <div class="flex items-center gap-2"
                                         x-data="{ raw: '{{ optional($teamBudgets[$team->id] ?? null)->budget }}' }">
                                        <span class="flex-1 text-sm text-gray-700 dark:text-gray-300 truncate">{{ $team->name }}</span>
                                        <div class="relative">
                                            <input type="number" min="0" step="any"
                                                   :value="raw === '' || raw === null ? '' : toM(raw)"
                                                   @input="raw = $event.target.value === '' ? '' : fromM($event.target.value)"
                                                   placeholder="Uniform"
                                                   class="form-control form-control-sm w-32 text-right pr-7">
                                            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs font-semibold text-gray-400 pointer-events-none">M</span>
                                        </div>
                                        <input type="hidden" name="team_budgets[{{ $team->id }}]" :value="raw">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
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
                                                <input type="number" step="any" min="0"
                                                       :value="toM(rule.from)"
                                                       @input="rule.from = fromM($event.target.value)"
                                                       class="form-control pr-16" required>
                                                <input type="hidden" :name="`bid_rules[${index}][from]`" :value="rule.from">
                                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-gray-400 pointer-events-none">M</span>
                                            </div>
                                        </div>

                                        {{-- To --}}
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">To Price</label>
                                            <div class="relative">
                                                <input type="number" step="any" min="0"
                                                       :value="toM(rule.to)"
                                                       @input="rule.to = fromM($event.target.value)"
                                                       class="form-control pr-16" required>
                                                <input type="hidden" :name="`bid_rules[${index}][to]`" :value="rule.to">
                                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-gray-400 pointer-events-none">M</span>
                                            </div>
                                        </div>

                                        {{-- Increment --}}
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Increment</label>
                                            <div class="relative">
                                                <input type="number" step="any" min="0"
                                                       :value="toM(rule.increment)"
                                                       @input="rule.increment = fromM($event.target.value)"
                                                       class="form-control pr-16" required>
                                                <input type="hidden" :name="`bid_rules[${index}][increment]`" :value="rule.increment">
                                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-gray-400 pointer-events-none">M</span>
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

                        {{-- Quick-bid steps: optional jump amounts the organizer can apply
                             instead of the standard increment for a single bid. --}}
                        <div class="mt-10 pt-8 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Quick-Bid Steps</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-4">
                                Optional jump amounts shown as buttons on the control panel, for when the room
                                moves faster than the standard increment. Leave empty to use only the ladder above.
                            </p>

                            <div class="space-y-3">
                                <template x-for="(step, index) in quickSteps" :key="index">
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm text-gray-500 w-6" x-text="'+' "></span>
                                        <div class="relative flex-1">
                                            <input type="number" min="0" step="any"
                                                   :value="toM(quickSteps[index])"
                                                   @input="quickSteps[index] = fromM($event.target.value)"
                                                   class="form-control pr-9 w-full" placeholder="e.g. 0.5">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-gray-400 pointer-events-none">M</span>
                                        </div>
                                        <input type="hidden" :name="`quick_bid_steps[${index}]`" :value="quickSteps[index]">
                                        <span class="text-sm text-gray-500 w-20" x-text="formatMoney(quickSteps[index])"></span>
                                        <button type="button" @click="removeQuickStep(index)"
                                                class="p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition"
                                                title="Remove step">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <button type="button" @click="addQuickStep()"
                                    class="mt-4 w-full py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl text-gray-500 dark:text-gray-400 hover:border-blue-500 hover:text-blue-500 transition-all flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Add Quick-Bid Step
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Step 4: Player Pool Management (same pool builder as Create) --}}
                <div x-show="step === 4" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                    <div class="mb-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Player Pool Management</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Group players into named pools, set the auction order per pool, and set base prices. Already-sold players are kept and not shown here.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mb-4">
                        <button type="button" @click="addPool()" class="btn btn-sm bg-indigo-600 hover:bg-indigo-700 text-white">
                            <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Add Pool
                        </button>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {{-- Pools (left) --}}
                        <div class="space-y-4">
                            <template x-for="(pool, idx) in pools" :key="pool.uid">
                                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-2xl p-4 border-2 border-blue-200 dark:border-blue-800">
                                    <div class="flex items-center gap-2 mb-3">
                                        <span class="text-xs uppercase tracking-wide text-gray-400" x-text="'#' + (idx + 1)"></span>
                                        <input type="text" x-model="pool.name" placeholder="Pool name"
                                               class="form-control form-control-sm flex-1 font-semibold bg-white dark:bg-gray-800">
                                        <button type="button" @click="removePool(idx)" class="text-red-500 text-xs hover:underline">Remove</button>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 mb-3">
                                        <div>
                                            <label class="text-[11px] text-gray-500">Capacity</label>
                                            <input type="number" min="1" x-model.number="pool.capacity" placeholder="e.g. 50"
                                                   class="form-control form-control-sm bg-white dark:bg-gray-800">
                                        </div>
                                        <div>
                                            <label class="text-[11px] text-gray-500">Auction order</label>
                                            <select x-model="pool.order_mode" class="form-control form-control-sm bg-white dark:bg-gray-800">
                                                <option value="sequential">Sequential (1,2,3…)</option>
                                                <option value="random">Random</option>
                                                <option value="odd_even">Odd then Even</option>
                                                <option value="manual">Custom (drag to order)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-gray-500 mb-2">
                                        <span x-text="pool.players.length"></span> players<span x-show="pool.order_mode==='manual'"> · drag ⠿ to set order</span>
                                    </p>
                                    <div class="space-y-2 max-h-80 overflow-y-auto" :data-pool-uid="pool.uid" x-init="$nextTick(() => initPoolSortable($el))">
                                        <template x-for="player in pool.players" :key="player.id">
                                            <div class="bg-white dark:bg-gray-800 rounded-lg p-2 border border-gray-200 dark:border-gray-700 flex items-center gap-2" :data-player-id="player.id">
                                                <span class="pool-player-handle cursor-move text-gray-400 select-none" x-show="pool.order_mode==='manual'">⠿</span>
                                                <div class="flex-1 min-w-0 flex items-center gap-1">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="player.name"></p>
                                                    <span x-show="player.retained" class="text-[10px] px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">retained</span>
                                                </div>
                                                <span class="relative w-24 flex-shrink-0">
                                                    <input type="number" step="any" min="0" placeholder="Base"
                                                           :value="toM(player.base_price)"
                                                           @input="player.base_price = fromM($event.target.value)"
                                                           class="form-control form-control-sm w-full text-center pr-5">
                                                    <span class="absolute right-1.5 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-gray-400 pointer-events-none">M</span>
                                                </span>
                                                <button type="button" @click="removeFromPool(player, idx)" class="text-red-500 px-1">✕</button>
                                            </div>
                                        </template>
                                        <p x-show="pool.players.length===0" class="text-xs text-gray-400 text-center py-4">No players yet — add from the right.</p>
                                    </div>
                                </div>
                            </template>
                            <p x-show="pools.length===0" class="text-sm text-gray-500 text-center py-6">No pools yet. Click <strong>Add Pool</strong> to start.</p>
                        </div>

                        {{-- Available Players (right) --}}
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-2xl p-5 border-2 border-green-200 dark:border-green-800">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900 dark:text-white">Available</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><span x-text="filteredAvailable.length"></span> approved &amp; not retained</p>
                                </div>
                            </div>

                            <div class="mb-3" x-show="pools.length > 0">
                                <label class="text-[11px] text-gray-500">Add to pool</label>
                                <select x-model="targetPool" class="form-control form-control-sm bg-white dark:bg-gray-800">
                                    <option value="">— Select a pool —</option>
                                    <template x-for="(pool, idx) in pools" :key="pool.uid">
                                        <option :value="String(idx)" x-text="pool.name || ('Pool ' + (idx+1))"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="relative mb-4">
                                <input type="text" x-model="searchAvailable" placeholder="Search available players..."
                                       class="form-control pl-10 bg-white dark:bg-gray-800">
                                <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>

                            <div class="flex justify-end mb-2" x-show="filteredAvailable.length > 0 && pools.length > 0">
                                <button type="button" @click="addAllPlayers()" class="text-xs text-green-700 dark:text-green-400 hover:underline">Add all (<span x-text="filteredAvailable.length"></span>) to selected pool</button>
                            </div>

                            <div class="space-y-2 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                                <template x-for="player in filteredAvailable" :key="player.id">
                                    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow group cursor-pointer"
                                         @click="addToPool(player)" :class="pools.length === 0 ? 'opacity-50 pointer-events-none' : ''">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white font-bold flex-shrink-0" x-text="player.name.charAt(0)"></div>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-semibold text-gray-900 dark:text-white truncate flex items-center gap-1">
                                                    <span x-text="player.name"></span>
                                                    <span x-show="player.retained" class="text-[10px] px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">retained</span>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400" x-text="pools.length === 0 ? 'Add a pool first' : 'Click to add to selected pool'"></p>
                                            </div>
                                            <div class="w-8 h-8 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 flex items-center justify-center group-hover:bg-green-500 group-hover:text-white transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <div x-show="filteredAvailable.length === 0" class="text-center py-12 px-4">
                                    <p class="text-gray-500 dark:text-gray-400" x-text="searchAvailable ? 'No players match that search.' : 'No players available to add.'"></p>
                                    {{-- Explain the filter rather than leaving a blank panel. --}}
                                    <p x-show="!searchAvailable" class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-xs mx-auto">
                                        Only players with an approved registration for this tournament appear here.
                                        Retained players are excluded — manage them on the
                                        <a href="{{ route('admin.auctions.pools.index', $auction) }}" class="text-indigo-500 underline">Pools</a> screen.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Serialized pools (written on submit) --}}
                    <input type="hidden" name="pools" x-ref="poolsInput">
                </div>

                {{-- Step 5: Branding --}}
                <div x-show="step === 5" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
                    <div class="max-w-4xl mx-auto">
                        <div class="mb-6">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Branding & Appearance</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Customize how the live auction page looks — background images, logos, and accent colors.</p>
                        </div>

                        @include('backend.pages.auctions.partials.screen-templates', [
                            'auctionId' => $auction->id,
                            'selectedDisplay' => old('auction_template_id', $auction->auction_template_id),
                            'selectedTicker' => old('ticker_template_id', $auction->ticker_template_id),
                            'displayTemplates' => $displayTemplates ?? collect(),
                            'tickerTemplates' => $tickerTemplates ?? collect(),
                        ])

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Card Background Image --}}
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Card Background Image</label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">This replaces the default player card background on the live page. Recommended: 1601x910px.</p>
                                <input type="file" name="background_image" accept="image/*"
                                       class="form-control" @change="previewImage($event, 'bgPreview')">
                                @if($auction->background_image)
                                    <div class="mt-3 flex items-end gap-3" id="current-background_image">
                                        <div>
                                            <p class="text-xs text-green-600 dark:text-green-400 mb-1">Current:</p>
                                            <img src="{{ $auction->background_image_url }}" alt="Card Background" class="h-32 rounded-lg border border-gray-200 dark:border-gray-700 object-cover">
                                        </div>
                                        <button type="button" @click="removeBrandingImage('background_image')"
                                                class="px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg text-sm font-medium hover:bg-red-200 dark:hover:bg-red-900/50 transition">
                                            Remove
                                        </button>
                                    </div>
                                @endif
                                <img id="bgPreview" class="mt-3 h-32 rounded-lg border border-gray-200 dark:border-gray-700 object-cover hidden">
                            </div>

                            {{-- Auction Logo --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Auction Logo</label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Shown on the live page card.</p>
                                <input type="file" name="auction_logo" accept="image/*"
                                       class="form-control" @change="previewImage($event, 'logoPreview')">
                                @if($auction->auction_logo)
                                    <div class="mt-3 flex items-end gap-3" id="current-auction_logo">
                                        <div>
                                            <p class="text-xs text-green-600 dark:text-green-400 mb-1">Current:</p>
                                            <img src="{{ $auction->auction_logo_url }}" alt="Auction Logo" class="h-20 rounded-lg border border-gray-200 dark:border-gray-700 object-contain">
                                        </div>
                                        <button type="button" @click="removeBrandingImage('auction_logo')"
                                                class="px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg text-sm font-medium hover:bg-red-200 dark:hover:bg-red-900/50 transition">
                                            Remove
                                        </button>
                                    </div>
                                @endif
                                <img id="logoPreview" class="mt-3 h-20 rounded-lg border border-gray-200 dark:border-gray-700 object-contain hidden">
                            </div>

                            {{-- Waiting Screen Background --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Waiting Screen Background</label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Background for the idle/waiting screen.</p>
                                <input type="file" name="waiting_background_image" accept="image/*"
                                       class="form-control" @change="previewImage($event, 'waitingBgPreview')">
                                @if($auction->waiting_background_image)
                                    <div class="mt-3 flex items-end gap-3" id="current-waiting_background_image">
                                        <div>
                                            <p class="text-xs text-green-600 dark:text-green-400 mb-1">Current:</p>
                                            <img src="{{ $auction->waiting_background_image_url }}" alt="Waiting BG" class="h-20 rounded-lg border border-gray-200 dark:border-gray-700 object-cover">
                                        </div>
                                        <button type="button" @click="removeBrandingImage('waiting_background_image')"
                                                class="px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg text-sm font-medium hover:bg-red-200 dark:hover:bg-red-900/50 transition">
                                            Remove
                                        </button>
                                    </div>
                                @endif
                                <img id="waitingBgPreview" class="mt-3 h-20 rounded-lg border border-gray-200 dark:border-gray-700 object-cover hidden">
                            </div>

                            {{-- Color Pickers --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Primary Color</label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Accent color for the live page (default: #00bcd4).</p>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="primary_color"
                                           value="{{ $auction->primary_color ?? '#00bcd4' }}"
                                           class="w-12 h-10 rounded cursor-pointer border border-gray-300 dark:border-gray-600">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 font-mono" id="primaryColorLabel">{{ $auction->primary_color ?? '#00bcd4' }}</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Secondary Color</label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Secondary accent color (default: #22c55e).</p>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="secondary_color"
                                           value="{{ $auction->secondary_color ?? '#22c55e' }}"
                                           class="w-12 h-10 rounded cursor-pointer border border-gray-300 dark:border-gray-600">
                                    <span class="text-sm text-gray-500 dark:text-gray-400 font-mono" id="secondaryColorLabel">{{ $auction->secondary_color ?? '#22c55e' }}</span>
                                </div>
                            </div>

                            {{-- Tournament Logo (read-only) --}}
                            @if($auction->tournament && $auction->tournament->logo_url)
                            <div class="md:col-span-2 bg-gray-50 dark:bg-gray-900/50 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tournament Logo (from tournament settings)</label>
                                <img src="{{ $auction->tournament->logo_url }}" alt="Tournament Logo" class="h-16 object-contain">
                                <p class="text-xs text-gray-400 mt-2">This logo is managed in Tournament settings and will also appear on the live page.</p>
                            </div>
                            @endif
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

                        <button type="button" @click="step++" x-show="step < 5"
                                class="flex-1 sm:flex-none btn btn-primary" x-cloak>
                            Next
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>

                        <button type="button" @click="submitForm()" x-show="step === 5"
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
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('auctionEditForm', (auctionData, availablePlayersData, existingPoolsData, unpooledData) => ({
        // State
        step: 1,
        auctionData: { ...auctionData },
        rules: [],
        // Optional quick-bid jump amounts (a flat list of numbers).
        quickSteps: [],
        // Pool builder state (same as create)
        pools: [],
        available: [],
        targetPool: '', // '' = no pool chosen (user must pick before adding)
        searchAvailable: '',
        selectedOrg: null,
        _uid: 1,
        isSubmitting: false,
        toast: { show: false, message: '', type: 'success' },

        // Step configuration
        steps: [
            { title: 'Details', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>' },
            { title: 'Financials', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' },
            { title: 'Bid Rules', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>' },
            { title: 'Players', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>' },
            { title: 'Branding', icon: '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>' }
        ],

        defaultBasePrice: Number(auctionData.base_price) || 10000,

        init() {
            // Ensure new fields have defaults for existing auctions
            if (!this.auctionData.bid_type) this.auctionData.bid_type = 'open';
            if (!this.auctionData.open_bid_mode) this.auctionData.open_bid_mode = 'online';
            if (!this.auctionData.bid_timer_seconds) this.auctionData.bid_timer_seconds = 30;
            if (!this.auctionData.bid_timer_reset_seconds) this.auctionData.bid_timer_reset_seconds = 15;
            if (!this.auctionData.timer_expiry_action) this.auctionData.timer_expiry_action = 'manual';
            if (!this.auctionData.amount_unit) this.auctionData.amount_unit = 'points';
            if (!this.auctionData.email_dispatch) this.auctionData.email_dispatch = 'deferred';
            // Older auctions predate these columns, so seed the same defaults the
            // server's accessors apply rather than leaving the inputs blank-but-bound.
            if (!this.auctionData.closed_bid_tie_breaker) this.auctionData.closed_bid_tie_breaker = 'lot';
            this.auctionData.closed_bid_requires_acceptance =
                auctionData.closed_bid_requires_acceptance === undefined || auctionData.closed_bid_requires_acceptance === null
                    ? true
                    : !!auctionData.closed_bid_requires_acceptance;
            this.auctionData.notifications_enabled = auctionData.notifications_enabled === undefined
                ? true
                : !!auctionData.notifications_enabled;
            this.auctionData.email_test_mode = !!auctionData.email_test_mode;
            if (!this.auctionData.final_call_interval_seconds) this.auctionData.final_call_interval_seconds = 3;
            this.auctionData.final_call_enabled = auctionData.final_call_enabled === undefined
                ? true
                : !!auctionData.final_call_enabled;
            // Checkbox binding needs a real boolean; the column defaults to true.
            this.auctionData.timer_enabled = auctionData.timer_enabled === undefined
                ? true
                : !!auctionData.timer_enabled;

            // Quick-bid steps repeater.
            this.quickSteps = Array.isArray(auctionData.quick_bid_steps)
                ? auctionData.quick_bid_steps.map(Number).filter(n => n > 0)
                : [];

            // Initialize bid rules
            this.rules = auctionData.bid_rules && auctionData.bid_rules.length > 0
                ? auctionData.bid_rules.map(rule => ({ ...rule }))
                : [{ from: 0, to: '', increment: '' }];

            // Seed pools from the auction's existing pools (lot order preserved).
            // Carrying `id`, `base_price` and `category` through is what makes the save
            // update each pool in place instead of recreating it and losing its
            // pools-screen settings.
            this.pools = (existingPoolsData || []).map(p => ({
                uid: this._uid++,
                id: p.id ?? null,
                name: p.name || ('Pool ' + this._uid),
                capacity: p.capacity || null,
                order_mode: p.order_mode || 'sequential',
                base_price: p.base_price ?? null,
                category: p.category ?? null,
                players: (p.players || []).map(pl => ({ id: pl.id, name: pl.name, org: pl.org, base_price: pl.base_price, retained: !!pl.retained })),
            }));

            // Players in the auction but not in any pool → drop into a pool so they aren't lost.
            const unpooled = (unpooledData || []).map(pl => ({ id: pl.id, name: pl.name, org: pl.org, base_price: pl.base_price, retained: !!pl.retained }));
            if (unpooled.length) {
                if (this.pools.length === 0) {
                    this.pools.push({ uid: this._uid++, id: null, name: 'Pool 1', capacity: null, order_mode: 'sequential', base_price: null, category: null, players: [] });
                }
                this.pools[0].players.push(...unpooled);
            }

            // If still no pools, start with one empty pool (like create).
            if (this.pools.length === 0) this.addPool();
            this.targetPool = ''; // no pool pre-selected on load

            // Available = approved players not already placed in a pool.
            const placed = new Set(this.pools.flatMap(p => p.players.map(pl => pl.id)));
            this.available = (availablePlayersData || [])
                .map(p => ({ id: p.id, name: p.name, org: p.organization_id, retained: !!p.retained }))
                .filter(p => !placed.has(p.id))
                .sort((a, b) => a.name.localeCompare(b.name));

            // Track the selected organization (Superadmin) so we never show another org's players.
            const orgSelect = document.getElementById('organization_id');
            const syncOrg = () => {
                this.selectedOrg = orgSelect && orgSelect.value ? Number(orgSelect.value) : Number(this.auctionData.organization_id) || null;
                this.dropForeignOrgPlayers();
            };
            if (orgSelect) {
                orgSelect.addEventListener('change', syncOrg);
            }
            this.selectedOrg = Number(this.auctionData.organization_id) || null;
        },

        get totalPooledPlayers() {
            return this.pools.reduce((n, p) => n + p.players.length, 0);
        },

        dropForeignOrgPlayers() {
            if (!this.selectedOrg) return;
            this.pools.forEach(pool => {
                pool.players = pool.players.filter(p => !p.org || p.org === this.selectedOrg);
            });
        },

        get filteredAvailable() {
            let list = this.available;
            if (this.selectedOrg) list = list.filter(p => !p.org || p.org === this.selectedOrg);
            if (this.searchAvailable) list = list.filter(p => p.name.toLowerCase().includes(this.searchAvailable.toLowerCase()));
            return list;
        },

        addPool() {
            // id: null marks this as a brand-new pool for the server to create.
            this.pools.push({ uid: this._uid++, id: null, name: 'Pool ' + (this.pools.length + 1), capacity: null, order_mode: 'sequential', base_price: null, category: null, players: [] });
            this.targetPool = String(this.pools.length - 1); // auto-target the just-created pool
        },

        removePool(idx) {
            this.pools[idx].players.forEach(p => this.available.push({ id: p.id, name: p.name, org: p.org, retained: p.retained }));
            this.available.sort((a, b) => a.name.localeCompare(b.name));
            this.pools.splice(idx, 1);
            this.targetPool = ''; // force an explicit re-pick after structure change
        },

        addToPool(player) {
            if (this.targetPool === '' || this.targetPool === null || this.pools.length === 0) {
                alert('Choose a pool in "Add to pool" first.');
                return;
            }
            this.pools[Number(this.targetPool)].players.push({ id: player.id, name: player.name, org: player.org, retained: player.retained, base_price: this.defaultBasePrice });
            this.available = this.available.filter(p => p.id !== player.id);
            this.searchAvailable = '';
        },

        removeFromPool(player, idx) {
            this.available.push({ id: player.id, name: player.name, org: player.org, retained: player.retained });
            this.available.sort((a, b) => a.name.localeCompare(b.name));
            this.pools[idx].players = this.pools[idx].players.filter(p => p.id !== player.id);
        },

        addAllPlayers() {
            if (this.targetPool === '' || this.targetPool === null || this.pools.length === 0) {
                alert('Choose a pool in "Add to pool" first.');
                return;
            }
            const i = Number(this.targetPool);
            const toAdd = this.filteredAvailable;
            if (!toAdd.length) return;
            if (!confirm('Add all ' + toAdd.length + ' players to ' + (this.pools[i].name || 'the pool') + '?')) return;
            const ids = new Set(toAdd.map(p => p.id));
            toAdd.forEach(p => this.pools[i].players.push({ id: p.id, name: p.name, org: p.org, retained: p.retained, base_price: this.defaultBasePrice }));
            this.available = this.available.filter(p => !ids.has(p.id));
        },

        initPoolSortable(el) {
            if (!window.Sortable || el._poolSortable) return;
            el._poolSortable = window.Sortable.create(el, {
                handle: '.pool-player-handle', animation: 150, draggable: '[data-player-id]',
                onEnd: (e) => {
                    const pool = this.pools.find(p => String(p.uid) === String(el.dataset.poolUid));
                    if (!pool || e.oldIndex === e.newIndex) return;
                    const moved = pool.players.splice(e.oldIndex, 1)[0];
                    pool.players.splice(e.newIndex, 0, moved);
                }
            });
        },

        serializePools() {
            const data = this.pools
                .filter(p => p.players.length > 0)
                .map(p => ({
                    // The pool id is what lets the server update pools in place rather
                    // than deleting and recreating them, which used to wipe each pool's
                    // base price, category, status and usage counters on every save.
                    id: p.id ?? null,
                    name: p.name || 'Pool',
                    capacity: p.capacity || null,
                    order_mode: p.order_mode || 'sequential',
                    base_price: p.base_price ?? null,
                    category: p.category ?? null,
                    players: p.players.map(pl => ({ id: pl.id, base_price: pl.base_price || this.defaultBasePrice })),
                }));
            this.$refs.poolsInput.value = JSON.stringify(data);
        },

        // Format money
        /** Shared K/M/B formatter with the chosen unit. */
        formatMoney(value) {
            return window.auctionAmount
                ? window.auctionAmount(value, this.unitConfig)
                : String(Number(value) || 0);
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

        /* ── Money entry in millions ───────────────────────────────────────────────
           Amounts are stored in whole units, but typing 100000000 is error-prone and
           unreadable, so every money field is entered in millions and converted here.
           The raw value stays in auctionData / rules and is what gets posted via a
           hidden input; only the visible field is scaled.                           */

        /** Raw stored units → the millions figure shown in the field. */
        toM(raw) {
            if (raw === '' || raw === null || raw === undefined) return '';
            const n = Number(raw);
            if (!isFinite(n)) return '';
            // Trim float noise (1e-7 style residue) without losing real precision.
            return Number((n / 1e6).toFixed(6));
        },

        /** Millions typed in the field → raw stored units. */
        fromM(value) {
            if (value === '' || value === null || value === undefined) return '';
            const n = Number(value);
            if (!isFinite(n)) return '';
            // toFixed(2) first: 0.1 * 1e6 is 100000.00000000001 in floating point.
            return Number((n * 1e6).toFixed(2));
        },

        /** The exact stored figure, echoed under the field so nothing is ambiguous. */
        rawLabel(raw) {
            if (raw === '' || raw === null || raw === undefined) return '—';
            return Number(raw).toLocaleString('en-US');
        },

        /** The auction's unit, shaped for the shared money formatter. */
        get unitConfig() {
            const unit = this.auctionData.amount_unit || 'points';
            if (unit === 'usd') return { label: '$', prefix: true };
            if (unit === 'coins') return { label: 'Coins', prefix: false };
            if (unit === 'custom') {
                return { label: (this.auctionData.amount_unit_label || '').trim() || 'Points', prefix: false };
            }
            return { label: 'Points', prefix: false };
        },

        /** Live example of how amounts will read with the chosen unit. */
        unitSample(value) {
            return window.auctionAmount
                ? window.auctionAmount(value, this.unitConfig)
                : String(value);
        },

        /* ── Squad reserve preview ── */

        /** Reserve held per unfilled place; blank falls back to the base price. */
        get reservePerPlace() {
            const explicit = Number(this.auctionData.min_price_per_player) || 0;
            return explicit > 0 ? explicit : (Number(this.auctionData.base_price) || 0);
        },

        /** What a full squad costs at that floor. */
        get reserveTotal() {
            const squad = Number(this.auctionData.min_squad_size) || {{ \App\Models\Auction::DEFAULT_MIN_SQUAD_SIZE }};
            return squad * this.reservePerPlace;
        },

        /** A squad that costs more than the purse makes the rule unsatisfiable. */
        get reserveExceedsBudget() {
            const budget = Number(this.auctionData.max_budget_per_team) || 0;
            return budget > 0 && this.reserveTotal > budget;
        },

        /* ── Retention preview ── */

        /** Blank falls back to 5M; an explicit 0 is honoured, so `??` not `||`. */
        get retentionPrice() {
            const raw = this.auctionData.default_retained_value;
            return raw === '' || raw === null || raw === undefined
                ? {{ \App\Models\Auction::DEFAULT_RETAINED_VALUE }}
                : (Number(raw) || 0);
        },

        /** What the expected retentions tie up before bidding opens. */
        get retentionCommitment() {
            const raw = this.auctionData.expected_retained_per_team;
            const count = raw === '' || raw === null || raw === undefined
                ? {{ \App\Models\Auction::DEFAULT_EXPECTED_RETAINED_PER_TEAM }}
                : (Number(raw) || 0);
            return count * this.retentionPrice;
        },

        /** Warned about, never refused — retentions are priced per player. */
        /* ── Sealed round preview ── */

        /** Blank falls back to 70%; the server applies the same default. */
        get sealedCapPct() {
            const raw = this.auctionData.closed_bid_max_pct_of_budget;
            return raw === '' || raw === null || raw === undefined
                ? {{ \App\Models\Auction::DEFAULT_CLOSED_BID_MAX_PCT }}
                : (Number(raw) || 0);
        },

        /** A share of the TOTAL budget, never of what is left. */
        get sealedPerPlayerCap() {
            return (Number(this.auctionData.max_budget_per_team) || 0) * this.sealedCapPct / 100;
        },

        /** The configuration the server refuses: no team could bid the opening amount. */
        get sealedCapBelowThreshold() {
            const threshold = Number(this.auctionData.closed_bid_starts_at) || 0;
            return threshold > 0 && this.sealedPerPlayerCap > 0 && threshold > this.sealedPerPlayerCap;
        },

        get retentionExceedsBudget() {
            const budget = Number(this.auctionData.max_budget_per_team) || 0;
            return budget > 0 && this.retentionCommitment > budget;
        },

        addQuickStep() {
            this.quickSteps.push('');
        },

        removeQuickStep(index) {
            this.quickSteps.splice(index, 1);
        },

        // Remove bid rule
        removeRule(index) {
            if (this.rules.length > 1) {
                this.rules.splice(index, 1);
            }
        },

        // Add player to auction
        // Remove a branding image via AJAX
        async removeBrandingImage(field) {
            if (!confirm('Remove this image?')) return;
            try {
                const response = await fetch(`/admin/auctions/{{ $auction->id }}/branding-image`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ field })
                });
                const data = await response.json();
                if (response.ok) {
                    const el = document.getElementById('current-' + field);
                    if (el) el.remove();
                    this.showToast('Image removed', 'success');
                } else {
                    this.showToast(data.error || 'Failed to remove', 'error');
                }
            } catch (error) {
                this.showToast('Network error', 'error');
            }
        },

        // Preview uploaded image
        previewImage(event, previewId) {
            const file = event.target.files[0];
            const preview = document.getElementById(previewId);
            if (file && preview) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        },

        // Submit form
        submitForm() {
            this.isSubmitting = true;
            this.serializePools();
            this.$refs.auctionFormElement.submit();
        }
    }));
});
</script>
@endpush
