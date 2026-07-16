@extends('backend.layouts.app')

@section('title', $otherTeam->name . ' - Players')

@section('admin-content')
<x-breadcrumbs :breadcrumbs="[
    ['name' => 'Dashboard', 'route' => route('team-manager.dashboard')],
    ['name' => 'Other Teams', 'route' => route('team-manager.other-teams')],
    ['name' => $otherTeam->name]
]" />

<div class="p-4 mx-auto max-w-7xl md:p-6">
    <div class="mb-6 flex items-center gap-4">
        @if($otherTeam->team_logo)
            <img src="{{ asset('storage/' . $otherTeam->team_logo) }}" alt="{{ $otherTeam->name }}" class="w-14 h-14 rounded-lg object-cover">
        @else
            <div class="w-14 h-14 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-lg font-bold">
                {{ strtoupper(substr($otherTeam->name, 0, 2)) }}
            </div>
        @endif
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $otherTeam->name }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $players->count() }} player{{ $players->count() !== 1 ? 's' : '' }} in squad</p>
        </div>
    </div>

    @if($players->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($players as $player)
            <div class="relative block rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-md transition-shadow">
                {{-- Wishlist heart — hide for retained players --}}
                @if($player->player_mode !== 'retained')
                    <button type="button" onclick="toggleWishlist({{ $player->id }}, this)"
                        class="wishlist-btn absolute top-3 right-3 z-10 p-1.5 rounded-full bg-black/20 hover:bg-black/30 transition-colors"
                        data-wishlisted="{{ in_array($player->id, $wishlistedIds) ? '1' : '0' }}">
                        <svg class="w-5 h-5 {{ in_array($player->id, $wishlistedIds) ? 'text-red-500 fill-red-500' : 'text-white' }}" fill="{{ in_array($player->id, $wishlistedIds) ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                    </button>
                @endif

                <a href="{{ route('team-manager.players.show', $player) }}">
                    {{-- Blue Gradient Header --}}
                    <div class="bg-gradient-to-r from-blue-600 to-cyan-700 p-4">
                        <div class="flex items-center gap-3">
                            @if($player->image_path)
                                <img src="{{ asset('storage/' . $player->image_path) }}" alt="{{ $player->name }}"
                                     class="w-14 h-14 rounded-full object-cover border-2 border-white/30 flex-shrink-0">
                            @else
                                <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                                    <span class="text-xl font-bold text-white">{{ strtoupper(substr($player->name, 0, 1)) }}</span>
                                </div>
                            @endif

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-bold text-white truncate">{{ $player->name }}</h3>
                                    @if($player->jersey_number)
                                        <span class="flex-shrink-0 inline-flex items-center justify-center px-2 py-0.5 rounded-full bg-white/20 text-xs font-bold text-white">#{{ $player->jersey_number }}</span>
                                    @endif
                                </div>
                                @if($player->playerType?->type)
                                    <p class="text-white/70 text-xs mt-0.5">{{ $player->playerType->type }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Stats Row --}}
                    <div class="bg-white dark:bg-gray-800 px-4 pt-3 pb-2">
                        <div class="flex items-center justify-around text-center">
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $player->total_matches ?? 0 }}</p>
                                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Matches</p>
                            </div>
                            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $player->total_runs ?? 0 }}</p>
                                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Runs</p>
                            </div>
                            <div class="w-px h-6 bg-gray-200 dark:bg-gray-700"></div>
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $player->total_wickets ?? 0 }}</p>
                                <p class="text-[10px] text-gray-400 uppercase tracking-wider">Wickets</p>
                            </div>
                        </div>
                    </div>

                    {{-- Badges Section --}}
                    <div class="bg-white dark:bg-gray-800 px-4 pb-3 pt-2">
                        <div class="flex flex-wrap gap-1.5">
                            @if($player->battingProfile?->style)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ $player->battingProfile->style }}</span>
                            @endif
                            @if($player->bowlingProfile?->style)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300">{{ $player->bowlingProfile->style }}</span>
                            @endif
                            @if($player->player_mode === 'retained')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gradient-to-r from-purple-500 to-violet-600 text-white">
                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                    Retained
                                </span>
                            @endif
                        </div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No players</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This team has no players in their squad yet.</p>
        </div>
    @endif

    <div class="mt-4">
        <a href="{{ route('team-manager.other-teams') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 inline-flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to Other Teams
        </a>
    </div>
</div>

<script>
    const toggleUrl = '{{ route("team-manager.wishlist.toggle") }}';
    const csrfToken = '{{ csrf_token() }}';

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
                svg.classList.remove('text-white');
                svg.classList.add('text-red-500', 'fill-red-500');
                svg.setAttribute('fill', 'currentColor');
                btn.dataset.wishlisted = '1';
            } else {
                svg.classList.remove('text-red-500', 'fill-red-500');
                svg.classList.add('text-white');
                svg.setAttribute('fill', 'none');
                btn.dataset.wishlisted = '0';
            }
        });
    }
</script>
@endsection
