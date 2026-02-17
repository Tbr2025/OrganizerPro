@extends('backend.layouts.app')

@section('title', 'Team Manager Dashboard')

@section('admin-content')
<div class="p-4 mx-auto max-w-7xl md:p-6">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Team Manager Dashboard</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Manage your team, players, and auctions</p>
    </div>

    {{-- Team Selector (if managing multiple teams) --}}
    @if($teams->count() > 1)
        <div class="mb-6">
            <label for="team-selector" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Team</label>
            <select id="team-selector" class="form-control max-w-xs" onchange="window.location.href='?team=' + this.value">
                @foreach($teams as $t)
                    <option value="{{ $t->id }}" {{ $team->id == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    {{-- Team Info Card --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Team Overview --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <div class="flex items-center gap-4 mb-4">
                @if($team->logo)
                    <img src="{{ asset('storage/' . $team->logo) }}" alt="{{ $team->name }}" class="w-16 h-16 rounded-lg object-cover">
                @else
                    <div class="w-16 h-16 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-xl font-bold">
                        {{ strtoupper(substr($team->name, 0, 2)) }}
                    </div>
                @endif
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $team->name }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $team->tournament->name ?? 'No Tournament' }}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 text-center">
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $teamPlayers->count() }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Players</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $upcomingAuctions->count() }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Auctions</p>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
            <div class="space-y-3">
                <a href="{{ route('team-manager.players.create') }}" class="btn btn-primary w-full flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Create New Player
                </a>
                <a href="{{ route('team-manager.auctions') }}" class="btn btn-secondary w-full flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    View Auctions
                </a>
            </div>
        </div>

        {{-- Budget Overview (for active auctions) --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Auction Budgets</h3>
            @forelse($upcomingAuctions as $auction)
                @php
                    $budget = $auctionBudgets[$auction->id] ?? ['max' => 0, 'spent' => 0, 'remaining' => 0];
                    $percentage = $budget['max'] > 0 ? ($budget['spent'] / $budget['max']) * 100 : 0;
                @endphp
                <div class="mb-4 last:mb-0">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-700 dark:text-gray-300">{{ $auction->title ?? 'Auction' }}</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ number_format($budget['remaining']) }} left</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: {{ min($percentage, 100) }}%"></div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ number_format($budget['spent']) }} / {{ number_format($budget['max']) }} spent
                    </p>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No active auctions</p>
            @endforelse
        </div>
    </div>

    {{-- Team Players Section --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md mb-8">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Team Roster</h3>
            <div class="flex gap-2">
                <button type="button" onclick="document.getElementById('add-player-modal').classList.remove('hidden')"
                    class="btn btn-secondary btn-sm">
                    Add Existing Player
                </button>
                <a href="{{ route('team-manager.players.create') }}" class="btn btn-primary btn-sm">
                    Create New Player
                </a>
            </div>
        </div>

        @if($teamPlayers->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3">Player</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Batting</th>
                            <th class="px-6 py-3">Bowling</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($teamPlayers as $player)
                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        @if($player->photo)
                                            <img src="{{ asset('storage/' . $player->photo) }}" alt="{{ $player->name }}" class="w-10 h-10 rounded-full object-cover">
                                        @else
                                            <div class="w-10 h-10 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center text-gray-600 dark:text-gray-300 font-bold">
                                                {{ strtoupper(substr($player->name, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $player->name }}</div>
                                            @if($player->jersey_number)
                                                <div class="text-xs text-gray-500">#{{ $player->jersey_number }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">{{ $player->playing_role ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $player->batting_style ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $player->bowling_style ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    @if($player->status === 'approved')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            Verified
                                        </span>
                                    @elseif($player->status === 'rejected')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            Rejected
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                            </svg>
                                            Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($player->status === 'pending')
                                            <button type="button"
                                                onclick="openVerifyModal({{ $player->id }}, '{{ addslashes($player->name) }}')"
                                                class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Verify
                                            </button>
                                        @endif
                                        @if($player->player_mode !== 'auctioned' && $player->player_mode !== 'retained')
                                            <form action="{{ route('team-manager.players.remove', $player) }}" method="POST" class="inline"
                                                  onsubmit="return confirm('Are you sure you want to remove this player from your team?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm">
                                                    Remove
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No players yet</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by adding players to your team.</p>
                <div class="mt-6 flex justify-center gap-3">
                    <button type="button" onclick="document.getElementById('add-player-modal').classList.remove('hidden')"
                        class="btn btn-secondary">
                        Add Existing Player
                    </button>
                    <a href="{{ route('team-manager.players.create') }}" class="btn btn-primary">
                        Create New Player
                    </a>
                </div>
            </div>
        @endif
    </div>

    {{-- Upcoming Auctions Section --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Upcoming Auctions</h3>
        </div>

        @if($upcomingAuctions->count() > 0)
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($upcomingAuctions as $auction)
                    <div class="p-6 flex items-center justify-between">
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">{{ $auction->title ?? 'Auction' }}</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $auction->tournament->name ?? '' }}</p>
                            <div class="mt-2 flex items-center gap-4">
                                @if($auction->status === 'active')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Live Now
                                    </span>
                                @elseif($auction->status === 'paused')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        Paused
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        Scheduled
                                    </span>
                                @endif
                                @php $budget = $auctionBudgets[$auction->id] ?? ['remaining' => 0]; @endphp
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    Budget: {{ number_format($budget['remaining']) }} remaining
                                </span>
                            </div>
                        </div>
                        @if($auction->status === 'active')
                            <a href="{{ route('team.auction.bidding.show', $auction) }}" class="btn btn-primary">
                                Join Bidding
                            </a>
                        @else
                            <span class="text-sm text-gray-400">Waiting to start</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No auctions</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">There are no upcoming auctions for your tournament.</p>
            </div>
        @endif
    </div>
</div>

{{-- Add Existing Player Modal --}}
<div id="add-player-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add Existing Player</h3>
            <button type="button" onclick="document.getElementById('add-player-modal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        @if($availablePlayers->count() > 0)
            <form action="{{ route('team-manager.players.add') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="player_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Select Player
                    </label>
                    <select name="player_id" id="player_id" class="form-control" required>
                        <option value="">Choose a player...</option>
                        @foreach($availablePlayers as $player)
                            <option value="{{ $player->id }}">
                                {{ $player->name }}
                                @if($player->playing_role) - {{ $player->playing_role }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('add-player-modal').classList.add('hidden')"
                        class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Add to Team
                    </button>
                </div>
            </form>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">No available players to add. All players in your organization are already assigned to teams.</p>
            <div class="flex justify-end">
                <button type="button" onclick="document.getElementById('add-player-modal').classList.add('hidden')"
                    class="btn btn-secondary">
                    Close
                </button>
            </div>
        @endif
    </div>
</div>

{{-- Verify Player Modal --}}
<div id="verify-player-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Verify Player</h3>
            <button type="button" onclick="closeVerifyModal()"
                class="text-gray-400 hover:text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form id="verify-player-form" method="POST">
            @csrf
            <div class="mb-4">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    You are about to verify player: <strong id="verify-player-name" class="text-gray-900 dark:text-white"></strong>
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Please enter your password to confirm this action.
                </p>
                <label for="verify-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Your Password
                </label>
                <input type="password" name="password" id="verify-password" required
                    class="form-control w-full"
                    placeholder="Enter your password">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeVerifyModal()"
                    class="btn btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Verify Player
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openVerifyModal(playerId, playerName) {
        document.getElementById('verify-player-name').textContent = playerName;
        document.getElementById('verify-player-form').action = '{{ url("admin/team-manager/players") }}/' + playerId + '/verify';
        document.getElementById('verify-password').value = '';
        document.getElementById('verify-player-modal').classList.remove('hidden');
    }

    function closeVerifyModal() {
        document.getElementById('verify-player-modal').classList.add('hidden');
    }
</script>
@endsection
