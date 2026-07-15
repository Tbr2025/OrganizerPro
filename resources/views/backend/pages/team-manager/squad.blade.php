@extends('backend.layouts.app')

@section('title', 'My Squad')

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Dashboard', 'route' => route('team-manager.dashboard')],
    ['name' => 'My Squad']
]" />

<div class="p-4 mx-auto max-w-7xl md:p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Squad</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $team->name }} - {{ $teamPlayers->count() }} players</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
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
                                        @if($player->image_path)
                                            <img src="{{ asset('storage/' . $player->image_path) }}" alt="{{ $player->name }}" class="w-10 h-10 rounded-full object-cover">
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
                                <td class="px-6 py-4">{{ $player->playerType->type ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $player->battingProfile->style ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $player->bowlingProfile->style ?? '-' }}</td>
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
                                        <button type="button" onclick="openViewPlayerModal({{ $player->id }})"
                                            class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-white bg-gray-600 hover:bg-gray-700 rounded-md">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            View
                                        </button>
                                        @if($player->status === 'pending')
                                            <button type="button"
                                                onclick="openVerifyModal({{ $player->id }}, '{{ addslashes($player->name) }}')"
                                                class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Verify
                                            </button>
                                            <form action="{{ route('team-manager.players.reject', $player) }}" method="POST" class="inline"
                                                  onsubmit="return confirm('Are you sure you want to reject {{ addslashes($player->name) }}?')">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-md">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                    Reject
                                                </button>
                                            </form>
                                        @endif
                                        @if($player->status === 'approved' && $player->email)
                                            <form action="{{ route('team-manager.players.resend-welcome', $player) }}" method="POST" class="inline"
                                                  onsubmit="return confirm('Resend welcome email to {{ addslashes($player->name) }}?')">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-md">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                    </svg>
                                                    Resend Email
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
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No players in your squad</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Players who register for your team will appear here.</p>
            </div>
        @endif
    </div>
</div>

{{-- Verify Player Modal --}}
<div id="verify-player-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Verify Player</h3>
            <button type="button" onclick="closeVerifyModal()" class="text-gray-400 hover:text-gray-500">
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
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Please enter your password to confirm this action.</p>
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
        ];
    })->keyBy('id');
@endphp

<script>
    const playersData = @json($playersJsonData);

    function openViewPlayerModal(playerId) {
        const player = playersData[playerId];
        if (!player) return;

        let html = `<div class="flex items-center gap-4 mb-4">`;
        if (player.image_path) {
            html += `<img src="${player.image_path}" alt="${player.name}" class="w-20 h-20 rounded-lg object-cover">`;
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
