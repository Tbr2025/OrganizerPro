@extends('backend.layouts.app')

@section('title', 'Team Manager Dashboard')

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Dashboard', 'route' => route('admin.dashboard')],
    ['name' => 'Team Manager']
]" />

<div class="p-4 mx-auto max-w-7xl md:p-6">

    {{-- Team Selector (if managing multiple teams) --}}
    @if($teams->count() > 1)
        <div class="mb-6">
            <select id="team-selector" class="form-control max-w-xs text-sm" onchange="window.location.href='?team=' + this.value">
                @foreach($teams as $t)
                    <option value="{{ $t->id }}" {{ $team->id == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    {{-- Team Hero Header --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-purple-600 to-indigo-800 p-6 md:p-8 mb-8">
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" viewBox="0 0 400 200" preserveAspectRatio="none"><path d="M0 100 Q100 20 200 100 T400 100 L400 200 L0 200 Z" fill="white"/></svg>
        </div>
        <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="flex items-center gap-5">
                @if($team->logo)
                    <img src="{{ asset('storage/' . $team->logo) }}" alt="{{ $team->name }}" class="w-20 h-20 rounded-xl object-cover border-2 border-white/30 shadow-lg">
                @else
                    <div class="w-20 h-20 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-white text-2xl font-bold border border-white/20">
                        {{ strtoupper(substr($team->name, 0, 2)) }}
                    </div>
                @endif
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-white">{{ $team->name }}</h1>
                    <p class="text-white/70 text-sm mt-1">{{ $team->tournament->name ?? 'No Tournament' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('team-manager.auctions') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white/15 hover:bg-white/25 backdrop-blur-sm text-white text-sm font-medium rounded-lg border border-white/20 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Auctions
                </a>
                @unless($managerIsPlayer)
                <a href="{{ route('team-manager.register-as-player') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-indigo-700 text-sm font-medium rounded-lg hover:bg-indigo-50 transition shadow">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Register as Player
                </a>
                @endunless
            </div>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $teamPlayers->count() }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Players</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $upcomingAuctions->count() }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Auctions</p>
                </div>
            </div>
        </div>
        @php
            $totalBudget = collect($auctionBudgets)->sum('max');
            $totalSpent = collect($auctionBudgets)->sum('spent');
        @endphp
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalBudget - $totalSpent) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Budget Left</p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalSpent) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Spent</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Left column: Registration + Auctions --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Player Registration Status --}}
            @if($managerIsPlayer && $managerRegistrations->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider">Your Player Registration</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($managerRegistrations as $reg)
                    <a href="{{ route('profileplayers.edit', ['registration_id' => $reg->id]) }}" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center">
                                <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $reg->tournament->name ?? 'Tournament' }}</span>
                        </div>
                        @if($reg->status === 'approved')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">Approved</span>
                        @elseif($reg->status === 'pending')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Pending</span>
                        @elseif($reg->status === 'rejected')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Rejected</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200">{{ ucfirst($reg->status) }}</span>
                        @endif
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Auction Budgets --}}
            @if($upcomingAuctions->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider">Auction Budgets</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($upcomingAuctions as $auction)
                    @php
                        $budget = $auctionBudgets[$auction->id] ?? ['max' => 0, 'spent' => 0, 'remaining' => 0];
                        $percentage = $budget['max'] > 0 ? ($budget['spent'] / $budget['max']) * 100 : 0;
                    @endphp
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $auction->title ?? $auction->tournament->name ?? 'Auction' }}</span>
                                @if($auction->status === 'running')
                                    <span class="relative flex h-2.5 w-2.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span></span>
                                @endif
                            </div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ number_format($budget['spent']) }} / {{ number_format($budget['max']) }}</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all {{ $percentage > 80 ? 'bg-red-500' : ($percentage > 50 ? 'bg-amber-500' : 'bg-indigo-500') }}" style="width: {{ min($percentage, 100) }}%"></div>
                        </div>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-xs text-gray-400">{{ number_format($budget['remaining']) }} remaining</span>
                            @if($auction->status === 'running')
                                <a href="{{ route('team.auction.bidding.show', $auction) }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Join Bidding &rarr;</a>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Upcoming Auctions --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider">Upcoming Auctions</h3>
                </div>
                @if($upcomingAuctions->count() > 0)
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($upcomingAuctions as $auction)
                    <div class="px-6 py-4 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0
                                {{ $auction->status === 'running' ? 'bg-green-100 dark:bg-green-900/40' : ($auction->status === 'paused' ? 'bg-amber-100 dark:bg-amber-900/40' : 'bg-blue-100 dark:bg-blue-900/40') }}">
                                <svg class="w-5 h-5 {{ $auction->status === 'running' ? 'text-green-600 dark:text-green-400' : ($auction->status === 'paused' ? 'text-amber-600 dark:text-amber-400' : 'text-blue-600 dark:text-blue-400') }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $auction->title ?? 'Auction' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $auction->tournament->name ?? '' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            @if($auction->status === 'running')
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Live
                                </span>
                                <a href="{{ route('team.auction.bidding.show', $auction) }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg transition">Join</a>
                            @elseif($auction->status === 'paused')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Paused</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Scheduled</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="px-6 py-10 text-center">
                    <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No upcoming auctions</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Right column: Team Leadership --}}
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider">Team Leadership</h3>
                </div>
                <div class="p-6 space-y-4">
                    @php
                        $owner = $teamMembers->first(fn($m) => $m->pivot->role === 'Owner');
                        $manager = $teamMembers->first(fn($m) => $m->pivot->role === 'Manager');
                        $captain = $teamMembers->first(fn($m) => strtolower($m->pivot->role) === 'captain');
                    @endphp

                    @if($owner)
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold shadow-sm">
                            {{ strtoupper(substr($owner->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $owner->name }}</p>
                            <span class="text-xs text-purple-600 dark:text-purple-400 font-medium">Owner</span>
                        </div>
                    </div>
                    @endif

                    @if($manager)
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center text-white text-sm font-bold shadow-sm">
                            {{ strtoupper(substr($manager->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $manager->name }}</p>
                            <span class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">Manager</span>
                        </div>
                    </div>
                    @endif

                    @if($captain)
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center text-white text-sm font-bold shadow-sm">
                            {{ strtoupper(substr($captain->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $captain->name }}</p>
                            <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">Captain</span>
                        </div>
                    </div>
                    @else
                    <p class="text-sm text-gray-400 dark:text-gray-500 italic">No captain assigned yet</p>
                    @endif

                    @if($isOwner || $isManager || $isCaptain)
                        @php
                            $assignableMembers = $teamMembers->filter(fn($m) => $m->id !== auth()->id() && !in_array($m->pivot->role, ['Owner', 'Manager']));
                        @endphp
                        @if($assignableMembers->isNotEmpty())
                        <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                            <form action="{{ route('team-manager.assign-captain') }}" method="POST"
                                  onsubmit="return confirm('{{ ($isOwner || $isManager) ? 'Assign this member as captain?' : 'Transfer captaincy? This cannot be undone by you.' }}')">
                                @csrf
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">
                                    {{ ($isOwner || $isManager) ? 'Assign Captain' : 'Transfer Captaincy' }}
                                </label>
                                <select name="new_captain_user_id" class="form-control text-sm mb-2 w-full" required>
                                    <option value="">Select member...</option>
                                    @foreach($assignableMembers as $member)
                                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-white transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                    {{ ($isOwner || $isManager) ? 'Assign' : 'Transfer' }}
                                </button>
                            </form>
                        </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Team Players Section (hidden for now — may be re-enabled later) --}}
{{-- <div>Team Roster</div> --}}

{{-- Verify Player Modal --}}
<div id="verify-player-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Verify Player</h3>
            <button type="button" onclick="closeVerifyModal()" class="text-gray-400 hover:text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="verify-player-form" method="POST">
            @csrf
            <div class="mb-4">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    You are about to verify player: <strong id="verify-player-name" class="text-gray-900 dark:text-white"></strong>
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Please enter your password to confirm.</p>
                <label for="verify-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Your Password</label>
                <input type="password" name="password" id="verify-password" required class="form-control w-full" placeholder="Enter your password">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeVerifyModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Verify Player</button>
            </div>
        </form>
    </div>
</div>

{{-- View Player Modal --}}
<div id="view-player-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Player Details</h3>
            <button type="button" onclick="document.getElementById('view-player-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div id="view-player-content" class="space-y-4 max-h-[70vh] overflow-y-auto"></div>
    </div>
</div>

@php
    $playersJsonData = $teamPlayers->map(function($player) {
        return [
            'id' => $player->id,
            'name' => $player->name,
            'email' => $player->email,
            'mobile_number_full' => $player->mobile_number_full,
            'cricheroes_number_full' => $player->cricheroes_number_full,
            'cricheroes_profile_url' => $player->cricheroes_profile_url,
            'jersey_name' => $player->jersey_name,
            'jersey_number' => $player->jersey_number,
            'player_type' => $player->playerType->type ?? null,
            'batting_profile' => $player->battingProfile->style ?? null,
            'bowling_profile' => $player->bowlingProfile->style ?? null,
            'is_wicket_keeper' => $player->is_wicket_keeper,
            'kit_size' => $player->kitSize->name ?? null,
            'location' => $player->location->name ?? null,
            'total_matches' => $player->total_matches,
            'total_runs' => $player->total_runs,
            'total_wickets' => $player->total_wickets,
            'transportation_required' => $player->transportation_required,
            'no_travel_plan' => $player->no_travel_plan,
            'travel_date_from' => $player->travel_date_from?->format('d M Y'),
            'travel_date_to' => $player->travel_date_to?->format('d M Y'),
            'status' => $player->status,
            'player_mode' => $player->player_mode,
            'retained_value' => $player->retained_value,
            'image_path' => $player->image_path ? asset('storage/' . $player->image_path) : null,
            'photo' => $player->photo ? asset('storage/' . $player->photo) : null,
        ];
    })->keyBy('id');
@endphp

<script>
    const playersData = @json($playersJsonData);

    function openViewPlayerModal(playerId) {
        const player = playersData[playerId];
        if (!player) return;
        const img = player.image_path || player.photo;
        let html = `<div class="flex items-center gap-4 mb-4">`;
        if (img) {
            html += `<img src="${img}" alt="${player.name}" class="w-20 h-20 rounded-lg object-cover">`;
        } else {
            html += `<div class="w-20 h-20 rounded-lg bg-gray-300 dark:bg-gray-600 flex items-center justify-center text-2xl font-bold text-gray-600 dark:text-gray-300">${player.name.charAt(0).toUpperCase()}</div>`;
        }
        html += `<div><h4 class="text-xl font-bold text-gray-900 dark:text-white">${player.name}</h4>
            <div class="flex gap-1 mt-1">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${player.status === 'approved' ? 'bg-green-100 text-green-800' : player.status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'}">${player.status.charAt(0).toUpperCase() + player.status.slice(1)}</span>
            ${player.player_mode === 'retained' ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-gradient-to-r from-purple-500 to-violet-600 text-white"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>Retained${player.retained_value ? ' (' + Number(player.retained_value).toLocaleString() + ')' : ''}</span>` : ''}
            </div>
        </div></div>`;
        const fields = [
            ['Email', player.email], ['Mobile', player.mobile_number_full], ['CricHeroes', player.cricheroes_number_full],
            ['CricHeroes URL', player.cricheroes_profile_url ? `<a href="${player.cricheroes_profile_url}" target="_blank" class="text-blue-600 hover:underline">${player.cricheroes_profile_url}</a>` : null],
            ['Location', player.location], ['Jersey Name', player.jersey_name], ['Jersey Number', player.jersey_number],
            ['Kit Size', player.kit_size], ['Player Type', player.player_type], ['Batting', player.batting_profile],
            ['Bowling', player.bowling_profile], ['Wicket Keeper', player.is_wicket_keeper ? 'Yes' : 'No'],
            ['Matches', player.total_matches], ['Runs', player.total_runs], ['Wickets', player.total_wickets],
            ['Transport Needed', player.transportation_required ? 'Yes' : 'No'],
            ['Travel Plan', player.no_travel_plan ? 'No travel plan' : (player.travel_date_from ? `${player.travel_date_from} - ${player.travel_date_to}` : null)],
        ];
        html += `<div class="grid grid-cols-2 gap-3">`;
        fields.forEach(([label, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                html += `<div><p class="text-xs text-gray-500 dark:text-gray-400">${label}</p><p class="text-sm font-medium text-gray-900 dark:text-white">${value}</p></div>`;
            }
        });
        html += `</div>`;
        document.getElementById('view-player-content').innerHTML = html;
        document.getElementById('view-player-modal').classList.remove('hidden');
    }

    function openVerifyModal(playerId, playerName) {
        document.getElementById('verify-player-name').textContent = playerName;
        document.getElementById('verify-player-form').action = '{{ url("/") }}/admin/team-manager/players/' + playerId + '/verify';
        document.getElementById('verify-password').value = '';
        document.getElementById('verify-player-modal').classList.remove('hidden');
    }

    function closeVerifyModal() {
        document.getElementById('verify-player-modal').classList.add('hidden');
    }
</script>
@endsection
