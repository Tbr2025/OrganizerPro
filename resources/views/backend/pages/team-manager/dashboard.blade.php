@extends('backend.layouts.app')

@section('title', 'Team Manager Dashboard')

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Dashboard', 'route' => route('admin.dashboard')],
    ['name' => 'Team Manager']
]" />

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
                <a href="{{ route('team-manager.auctions') }}" class="btn btn-secondary w-full flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    View Auctions
                </a>
                @unless($managerIsPlayer)
                <a href="{{ route('team-manager.register-as-player') }}" class="btn btn-success w-full flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Register as Player
                </a>
                @endunless
            </div>

            {{-- Player Registration Status --}}
            @if($managerIsPlayer && $managerRegistrations->isNotEmpty())
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Your Player Registration</h4>
                    <div class="space-y-2">
                        @foreach($managerRegistrations as $reg)
                            <a href="{{ route('profileplayers.edit', ['registration_id' => $reg->id]) }}" class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <span class="text-sm text-gray-700 dark:text-gray-300 truncate mr-2">{{ $reg->tournament->name ?? 'Tournament' }}</span>
                                @if($reg->status === 'approved')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 whitespace-nowrap">Approved</span>
                                @elseif($reg->status === 'pending')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 whitespace-nowrap">Pending</span>
                                @elseif($reg->status === 'rejected')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 whitespace-nowrap">Rejected</span>
                                @elseif($reg->status === 'queued')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 whitespace-nowrap">Queued</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200 whitespace-nowrap">{{ ucfirst($reg->status) }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

        {{-- Team Owner & Captain --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Team Leadership</h3>
            @php
                $owner = $teamMembers->first(fn($m) => $m->pivot->role === 'Owner');
                $manager = $teamMembers->first(fn($m) => $m->pivot->role === 'Manager');
                $captain = $teamMembers->first(fn($m) => strtolower($m->pivot->role) === 'captain');
            @endphp

            {{-- Team Owner --}}
            @if($owner)
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center text-purple-600 dark:text-purple-300 font-bold">
                        {{ strtoupper(substr($owner->name, 0, 1)) }}
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $owner->name }}</p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                            Owner
                        </span>
                    </div>
                </div>
            @endif

            {{-- Team Manager --}}
            @if($manager)
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-600 dark:text-indigo-300 font-bold">
                        {{ strtoupper(substr($manager->name, 0, 1)) }}
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $manager->name }}</p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                            Manager
                        </span>
                    </div>
                </div>
            @endif

            {{-- Captain --}}
            @if($captain)
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-yellow-100 dark:bg-yellow-900 flex items-center justify-center text-yellow-600 dark:text-yellow-300 font-bold">
                        {{ strtoupper(substr($captain->name, 0, 1)) }}
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $captain->name }}</p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                            Captain
                        </span>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">No captain assigned yet</p>
            @endif

            @if($isOwner || $isManager || $isCaptain)
                @php
                    $assignableMembers = $teamMembers->filter(fn($m) => $m->id !== auth()->id() && !in_array($m->pivot->role, ['Owner', 'Manager']));
                @endphp
                @if($assignableMembers->isNotEmpty())
                    <form action="{{ route('team-manager.assign-captain') }}" method="POST"
                          onsubmit="return confirm('{{ ($isOwner || $isManager) ? 'Are you sure you want to assign this member as captain?' : 'Are you sure you want to transfer captaincy? This action cannot be undone by you.' }}')">
                        @csrf
                        <label for="new_captain_user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ ($isOwner || $isManager) ? 'Assign Captain' : 'Transfer Captaincy To' }}
                        </label>
                        <select name="new_captain_user_id" id="new_captain_user_id" class="form-control mb-3" required>
                            <option value="">Select a member...</option>
                            @foreach($assignableMembers as $member)
                                <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->pivot->role }})</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-warning w-full flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                            </svg>
                            {{ ($isOwner || $isManager) ? 'Assign Captain' : 'Transfer Captaincy' }}
                        </button>
                    </form>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">No other team members to assign as captain.</p>
                @endif
            @endif
        </div>
    </div>

    {{-- Budget Overview (for active auctions) --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-8">
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

    {{-- Team Players Section (hidden for now — may be re-enabled later) --}}
    {{--
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md mb-8">
        ... Team Roster ...
    </div>
    --}}

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

{{-- View Player Modal --}}
<div id="view-player-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Player Details</h3>
            <button type="button" onclick="document.getElementById('view-player-modal').classList.add('hidden')"
                class="text-gray-400 hover:text-gray-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
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
        html += `<div>
            <h4 class="text-xl font-bold text-gray-900 dark:text-white">${player.name}</h4>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${player.status === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : player.status === 'rejected' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'}">${player.status.charAt(0).toUpperCase() + player.status.slice(1)}</span>
        </div></div>`;

        const fields = [
            ['Email', player.email],
            ['Mobile', player.mobile_number_full],
            ['CricHeroes', player.cricheroes_number_full],
            ['CricHeroes URL', player.cricheroes_profile_url ? `<a href="${player.cricheroes_profile_url}" target="_blank" class="text-blue-600 hover:underline">${player.cricheroes_profile_url}</a>` : null],
            ['Location', player.location],
            ['Jersey Name', player.jersey_name],
            ['Jersey Number', player.jersey_number],
            ['Kit Size', player.kit_size],
            ['Player Type', player.player_type],
            ['Batting', player.batting_profile],
            ['Bowling', player.bowling_profile],
            ['Wicket Keeper', player.is_wicket_keeper ? 'Yes' : 'No'],
            ['Matches', player.total_matches],
            ['Runs', player.total_runs],
            ['Wickets', player.total_wickets],
            ['Transport Needed', player.transportation_required ? 'Yes' : 'No'],
            ['Travel Plan', player.no_travel_plan ? 'No travel plan' : (player.travel_date_from ? `${player.travel_date_from} - ${player.travel_date_to}` : null)],
        ];

        html += `<div class="grid grid-cols-2 gap-3">`;
        fields.forEach(([label, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                html += `<div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">${label}</p>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">${value}</p>
                </div>`;
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
