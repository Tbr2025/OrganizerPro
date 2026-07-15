@extends('backend.layouts.app')

@section('title', 'Players')

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Dashboard', 'route' => route('team-manager.dashboard')],
    ['name' => 'Players']
]" />

<div class="p-4 mx-auto max-w-7xl md:p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Players</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">All approved players in {{ $team->tournament->name ?? 'the tournament' }}</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
        @if($players->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3">Player</th>
                            <th class="px-6 py-3">Team</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Batting</th>
                            <th class="px-6 py-3">Bowling</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($players as $player)
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
                                <td class="px-6 py-4">{{ $player->actualTeam->name ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $player->playerType->type ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $player->battingProfile->style ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $player->bowlingProfile->style ?? '-' }}</td>
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
                                        <button type="button" onclick="toggleWishlist({{ $player->id }}, this)"
                                            class="wishlist-btn p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                            data-wishlisted="{{ in_array($player->id, $wishlistedIds) ? '1' : '0' }}">
                                            <svg class="w-5 h-5 {{ in_array($player->id, $wishlistedIds) ? 'text-red-500 fill-red-500' : 'text-gray-400' }}" fill="{{ in_array($player->id, $wishlistedIds) ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                            </svg>
                                        </button>
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
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No players found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No approved players in this tournament yet.</p>
            </div>
        @endif
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
    $playersJsonData = $players->map(function($player) {
        return [
            'id' => $player->id,
            'name' => $player->name,
            'email' => $player->email,
            'mobile_number_full' => $player->mobile_number_full,
            'jersey_name' => $player->jersey_name,
            'jersey_number' => $player->jersey_number,
            'player_type' => $player->playerType->type ?? null,
            'batting_profile' => $player->battingProfile->style ?? null,
            'bowling_profile' => $player->bowlingProfile->style ?? null,
            'is_wicket_keeper' => $player->is_wicket_keeper,
            'team_name' => $player->actualTeam->name ?? null,
            'total_matches' => $player->total_matches,
            'total_runs' => $player->total_runs,
            'total_wickets' => $player->total_wickets,
            'image_path' => $player->image_path ? asset('storage/' . $player->image_path) : null,
        ];
    })->keyBy('id');
@endphp

<script>
    const playersData = @json($playersJsonData);
    const toggleUrl = '{{ route("team-manager.wishlist.toggle") }}';
    const csrfToken = '{{ csrf_token() }}';

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
            ${player.team_name ? `<p class="text-sm text-gray-500 dark:text-gray-400">${player.team_name}</p>` : ''}
        </div></div>`;

        const fields = [
            ['Player Type', player.player_type],
            ['Batting', player.batting_profile],
            ['Bowling', player.bowling_profile],
            ['Wicket Keeper', player.is_wicket_keeper ? 'Yes' : 'No'],
            ['Jersey Name', player.jersey_name],
            ['Jersey Number', player.jersey_number],
            ['Matches', player.total_matches],
            ['Runs', player.total_runs],
            ['Wickets', player.total_wickets],
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

    function toggleWishlist(playerId, btn) {
        fetch(toggleUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ player_id: playerId })
        })
        .then(r => r.json())
        .then(data => {
            const svg = btn.querySelector('svg');
            if (data.wishlisted) {
                svg.classList.remove('text-gray-400');
                svg.classList.add('text-red-500', 'fill-red-500');
                svg.setAttribute('fill', 'currentColor');
                btn.dataset.wishlisted = '1';
            } else {
                svg.classList.remove('text-red-500', 'fill-red-500');
                svg.classList.add('text-gray-400');
                svg.setAttribute('fill', 'none');
                btn.dataset.wishlisted = '0';
            }
        });
    }
</script>
@endsection
