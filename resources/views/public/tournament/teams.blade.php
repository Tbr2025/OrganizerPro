@extends('public.tournament.layouts.app')

@section('title', 'Teams - ' . $tournament->name)

@push('styles')
<style>
    .page-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 50%, var(--primary) 100%);
    }
    .team-card {
        background: linear-gradient(145deg, var(--secondary) 0%, var(--primary) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.4s ease;
    }
    .team-card:hover {
        transform: translateY(-8px);
        border-color: rgba(var(--accent-rgb), 0.5);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), 0 0 30px rgba(var(--accent-rgb), 0.1);
    }
    .team-logo-container {
        background: linear-gradient(145deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.02) 100%);
        border: 2px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
    }
    .team-card:hover .team-logo-container {
        border-color: rgba(var(--accent-rgb), 0.5);
        box-shadow: 0 0 30px rgba(var(--accent-rgb), 0.3), 0 0 60px rgba(var(--accent-rgb), 0.1);
    }
    .stat-item {
        background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
        border-radius: 12px;
        padding: 16px 12px;
    }
    .squad-header {
        background: linear-gradient(90deg, rgba(var(--accent-rgb), 0.1) 0%, transparent 100%);
    }
    .player-row {
        transition: all 0.2s ease;
    }
    .player-row:hover {
        background: rgba(var(--accent-rgb), 0.1);
    }
    .captain-badge {
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
        color: #1f2937;
    }
    .vice-captain-badge {
        background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        color: white;
    }
    .jersey-number {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 4px 10px;
    }
    .qualified-badge {
        background: linear-gradient(135deg, rgba(34, 197, 94, 0.3) 0%, rgba(34, 197, 94, 0.1) 100%);
        border: 1px solid rgba(34, 197, 94, 0.5);
        color: #4ade80;
    }
    .position-badge {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: bold;
        font-size: 12px;
    }
    .position-1 { background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%); color: #1f2937; }
    .position-2 { background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%); color: white; }
    .position-3 { background: linear-gradient(135deg, #cd7f32 0%, #b8860b 100%); color: white; }
    .position-other { background: rgba(255, 255, 255, 0.1); color: #9ca3af; }
    .player-avatar {
        background: linear-gradient(145deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.02) 100%);
    }
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .animate-fadeInUp {
        animation: fadeInUp 0.5s ease forwards;
    }
</style>
@endpush

@section('content')
    {{-- Page Header --}}
    <section class="page-header py-16 relative overflow-hidden">
        <div class="absolute inset-0 opacity-30">
            <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 right-1/4 w-96 h-96 rounded-full blur-3xl" style="background: rgba(var(--accent-rgb), 0.2);"></div>
        </div>
        <div class="relative max-w-6xl mx-auto px-4 reveal">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <span class="inline-block px-4 py-2 bg-blue-500/20 text-blue-400 rounded-full text-sm font-semibold mb-4">
                        <i class="fas fa-users mr-2"></i>Squads
                    </span>
                    <h1 class="text-4xl md:text-5xl font-bold text-white">Participating Teams</h1>
                    <p class="text-gray-400 mt-2">{{ $teams->count() }} {{ Str::plural('team', $teams->count()) }} competing</p>
                </div>
                <div>
                    @php
                        $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                        $shareMessage = "Teams - {$tournament->name}\n\n" . request()->url();
                    @endphp
                    <x-share-buttons
                        :title="'Teams - ' . $tournament->name"
                        :description="$tournament->name . ' participating teams'"
                        :whatsappMessage="$shareMessage"
                        variant="compact"
                        :showLabel="false"
                    />
                </div>
            </div>
        </div>
    </section>

    {{-- Teams Grid --}}
    <section class="py-12 min-h-screen" style="background-color: var(--primary);">
        <div class="max-w-6xl mx-auto px-4">
            @if($teams->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 stagger-children">
                    @foreach($teams as $index => $team)
                        @php
                            $teamEntry = $tournament->pointTableEntries->where('actual_team_id', $team->id)->first();
                            $position = $teamEntry?->position;
                            $squadPlayers = $team->playersPerTournament;
                        @endphp
                        <div class="team-card tilt-card rounded-2xl overflow-hidden">
                            {{-- Team Header --}}
                            <div class="p-6 text-center relative">
                                {{-- Position Badge (if in point table) --}}
                                @if($position)
                                    <div class="absolute top-4 left-4">
                                        <div class="position-badge {{ $position <= 3 ? 'position-' . $position : 'position-other' }}">
                                            {{ $position }}
                                        </div>
                                    </div>
                                @endif

                                {{-- Qualified Badge --}}
                                @if($teamEntry?->qualified)
                                    <div class="absolute top-4 right-4">
                                        <span class="qualified-badge text-xs font-semibold px-2 py-1 rounded">
                                            <i class="fas fa-check-circle mr-1"></i>Q
                                        </span>
                                    </div>
                                @endif

                                {{-- Team Logo --}}
                                <div class="team-logo-container w-28 h-28 rounded-full mx-auto mb-4 flex items-center justify-center overflow-hidden">
                                    @if($team->team_logo)
                                        <img src="{{ Storage::url($team->team_logo) }}" alt="{{ $team->name }}" class="w-20 h-20 object-contain">
                                    @else
                                        <span class="text-3xl font-bold text-gray-400">{{ $team->short_name ?? substr($team->name, 0, 2) }}</span>
                                    @endif
                                </div>

                                {{-- Team Name --}}
                                <h2 class="text-xl font-bold text-white">{{ $team->name }}</h2>
                                @if($team->short_name)
                                    <p class="text-gray-500 text-sm">({{ $team->short_name }})</p>
                                @endif
                            </div>

                            {{-- Team Stats --}}
                            <div class="px-4 pb-4">
                                <div class="grid grid-cols-4 gap-2">
                                    <div class="stat-item text-center">
                                        <p class="text-xl font-bold text-blue-400 count-up" data-count="{{ $squadPlayers->count() }}">0</p>
                                        <p class="text-xs text-gray-500">Players</p>
                                    </div>
                                    <div class="stat-item text-center">
                                        <p class="text-xl font-bold text-gray-300 count-up" data-count="{{ $teamEntry?->matches_played ?? 0 }}">0</p>
                                        <p class="text-xs text-gray-500">Played</p>
                                    </div>
                                    <div class="stat-item text-center">
                                        <p class="text-xl font-bold text-green-400 count-up" data-count="{{ $teamEntry?->won ?? 0 }}">0</p>
                                        <p class="text-xs text-gray-500">Won</p>
                                    </div>
                                    <div class="stat-item text-center">
                                        <p class="text-xl font-bold text-red-400 count-up" data-count="{{ $teamEntry?->lost ?? 0 }}">0</p>
                                        <p class="text-xs text-gray-500">Lost</p>
                                    </div>
                                </div>

                                {{-- Points & NRR Row --}}
                                @if($teamEntry)
                                    <div class="mt-3 flex justify-between items-center bg-gray-800/50 rounded-xl px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-500 text-sm">Points:</span>
                                            <span class="text-accent font-bold text-lg">{{ $teamEntry->points }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-500 text-sm">NRR:</span>
                                            <span class="font-mono font-semibold text-sm {{ $teamEntry->net_run_rate >= 0 ? 'text-green-400' : 'text-red-400' }}">
                                                {{ $teamEntry->net_run_rate >= 0 ? '+' : '' }}{{ number_format($teamEntry->net_run_rate, 3) }}
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- View Squad Button + Modal --}}
                            <div x-data="{ showSquad: false }" class="border-t border-gray-700/50">
                                <button @click="showSquad = true"
                                        class="squad-header w-full px-4 py-4 flex justify-between items-center text-gray-300 hover:text-white transition">
                                    <span class="font-semibold flex items-center gap-2">
                                        <i class="fas fa-shirt text-accent"></i>
                                        View Squad
                                        <span class="text-gray-500 text-sm font-normal">({{ $squadPlayers->count() }})</span>
                                    </span>
                                    <i class="fas fa-arrow-right text-sm"></i>
                                </button>

                                {{-- Squad Modal (teleported to body to avoid overflow clipping) --}}
                                <template x-teleport="body">
                                    <div x-show="showSquad" x-cloak
                                         class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                                         @keydown.escape.window="showSquad = false"
                                         style="background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);">
                                        <div class="w-full max-w-lg max-h-[80vh] rounded-2xl overflow-hidden"
                                             style="background: linear-gradient(145deg, var(--secondary) 0%, var(--primary) 100%); border: 1px solid rgba(255,255,255,0.1);"
                                             @click.outside="showSquad = false">

                                            {{-- Modal Header --}}
                                            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-700/50">
                                                <div class="flex items-center gap-3">
                                                    @if($team->team_logo)
                                                        <img src="{{ Storage::url($team->team_logo) }}" alt="{{ $team->name }}" class="w-8 h-8 object-contain">
                                                    @endif
                                                    <div>
                                                        <h3 class="text-lg font-bold text-white">{{ $team->name }}</h3>
                                                        <p class="text-xs text-gray-400">{{ $squadPlayers->count() }} {{ Str::plural('Player', $squadPlayers->count()) }}</p>
                                                    </div>
                                                </div>
                                                <button @click="showSquad = false" class="text-gray-400 hover:text-white transition p-1">
                                                    <i class="fas fa-times text-lg"></i>
                                                </button>
                                            </div>

                                            {{-- Modal Body (scrollable) --}}
                                            <div class="overflow-y-auto px-4 py-3 space-y-1" style="max-height: calc(80vh - 70px);">
                                                @forelse($squadPlayers as $teamPlayer)
                                                    <div class="player-row py-3 px-3 rounded-lg">
                                                        {{-- Top row: Image + Name + Role --}}
                                                        <div class="flex items-center gap-3 min-w-0">
                                                            @if($teamPlayer->image_path)
                                                                <img src="{{ Storage::url($teamPlayer->image_path) }}"
                                                                     alt="{{ $teamPlayer->name }}"
                                                                     class="h-10 w-10 rounded-full object-cover border-2 border-gray-700 flex-shrink-0">
                                                            @else
                                                                <div class="player-avatar h-10 w-10 rounded-full flex items-center justify-center border-2 border-gray-700 flex-shrink-0">
                                                                    <i class="fas fa-user text-gray-500"></i>
                                                                </div>
                                                            @endif
                                                            <div class="min-w-0">
                                                                <div class="flex items-center gap-2">
                                                                    <p class="font-medium text-white truncate">{{ $teamPlayer->name }}</p>
                                                                    @if($teamPlayer->pivot->role === 'captain')
                                                                        <span class="captain-badge text-xs font-bold px-2 py-0.5 rounded flex-shrink-0">
                                                                            <i class="fas fa-crown mr-1"></i>C
                                                                        </span>
                                                                    @elseif($teamPlayer->pivot->role === 'vice_captain')
                                                                        <span class="vice-captain-badge text-xs font-bold px-2 py-0.5 rounded flex-shrink-0">
                                                                            VC
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {{-- Player Profile Card --}}
                                                        @php
                                                            $playerTypeName = $teamPlayer->playerType ? ($teamPlayer->playerType->name ?? $teamPlayer->playerType->type ?? '') : '';
                                                            $battingName = $teamPlayer->battingProfile->style ?? $teamPlayer->battingProfile->name ?? '';
                                                            $bowlingName = $teamPlayer->bowlingProfile->style ?? $teamPlayer->bowlingProfile->name ?? '';
                                                            $battingPositions = is_array($teamPlayer->preferred_batting_positions) ? array_filter($teamPlayer->preferred_batting_positions) : [];
                                                            $hasProfile = $playerTypeName || $battingName || $teamPlayer->batting_mode || $bowlingName || $battingPositions || $teamPlayer->is_wicket_keeper;
                                                        @endphp
                                                        @if($hasProfile)
                                                            <div class="mt-2 rounded-lg px-3 py-2" style="margin-left: 3.25rem; background: rgba(31, 41, 55, 0.6);">
                                                                <div class="flex flex-wrap items-center gap-1.5">
                                                                    @if($playerTypeName)
                                                                        <span class="text-xs font-medium text-blue-300 px-2 py-0.5 rounded-full" style="background: rgba(59, 130, 246, 0.2);">
                                                                            <i class="fas fa-user-tag mr-1 opacity-70"></i>{{ $playerTypeName }}
                                                                        </span>
                                                                    @endif
                                                                    @if($battingName)
                                                                        <span class="text-xs font-medium text-amber-300 px-2 py-0.5 rounded-full" style="background: rgba(245, 158, 11, 0.2);">
                                                                            <i class="fas fa-baseball-ball mr-1 opacity-70"></i>{{ $battingName }}
                                                                        </span>
                                                                    @endif
                                                                    @if($teamPlayer->batting_mode)
                                                                        <span class="text-xs font-medium text-orange-300 px-2 py-0.5 rounded-full" style="background: rgba(249, 115, 22, 0.2);">
                                                                            <i class="fas fa-bolt mr-1 opacity-70"></i>{{ $teamPlayer->batting_mode }}
                                                                        </span>
                                                                    @endif
                                                                    @if($battingPositions)
                                                                        <span class="text-xs font-medium text-cyan-300 px-2 py-0.5 rounded-full" style="background: rgba(6, 182, 212, 0.2);">
                                                                            <i class="fas fa-list-ol mr-1 opacity-70"></i>{{ implode(', ', $battingPositions) }}
                                                                        </span>
                                                                    @endif
                                                                    @if($bowlingName)
                                                                        <span class="text-xs font-medium text-green-300 px-2 py-0.5 rounded-full" style="background: rgba(34, 197, 94, 0.2);">
                                                                            <i class="fas fa-bowling-ball mr-1 opacity-70"></i>{{ $bowlingName }}
                                                                        </span>
                                                                    @endif
                                                                    @if($teamPlayer->is_wicket_keeper)
                                                                        <span class="text-xs font-medium text-purple-300 px-2 py-0.5 rounded-full" style="background: rgba(168, 85, 247, 0.2);">
                                                                            <i class="fas fa-mitten mr-1 opacity-70"></i>WK
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @empty
                                                    <div class="text-center py-8">
                                                        <i class="fas fa-user-slash text-2xl text-gray-600 mb-2"></i>
                                                        <p class="text-gray-500 text-sm">No players assigned yet</p>
                                                    </div>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                {{-- Empty State --}}
                <div class="text-center py-20">
                    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gray-800 flex items-center justify-center">
                        <i class="fas fa-users text-4xl text-gray-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-2">Teams Coming Soon</h3>
                    <p class="text-gray-400">Participating teams will be announced shortly.</p>
                </div>
            @endif
        </div>
    </section>

    {{-- Tournament Info Footer --}}
    @if($teams->count() > 0)
        <section class="py-12" style="background-color: var(--secondary);">
            <div class="max-w-4xl mx-auto px-4 text-center">
                <div class="bg-gradient-to-r from-blue-600/20 to-indigo-600/20 rounded-2xl p-8 border border-blue-500/30">
                    <i class="fas fa-trophy text-3xl text-accent mb-4"></i>
                    <h3 class="text-xl font-bold text-white mb-2">Tournament Overview</h3>
                    <p class="text-gray-300">
                        {{ $teams->count() }} teams competing for glory in {{ $tournament->name }}.
                        @if($tournament->pointTableEntries->count() > 0)
                            Check the <a href="{{ route('public.tournament.point-table', $tournament->slug) }}" class="accent-link hover:underline">Point Table</a> for current standings.
                        @endif
                    </p>
                </div>
            </div>
        </section>
    @endif
@endsection
