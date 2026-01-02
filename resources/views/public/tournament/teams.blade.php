@extends('public.tournament.layouts.app')

@section('title', 'Teams - ' . $tournament->name)

@section('content')
    <div class="max-w-6xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8 text-center">Participating Teams</h1>

        @if($teams->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($teams as $team)
                    <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700 hover:border-yellow-500 transition">
                        {{-- Team Header --}}
                        <div class="p-6 text-center border-b border-gray-700">
                            @if($team->logo)
                                <img src="{{ Storage::url($team->logo) }}" alt="{{ $team->name }}" class="h-24 w-24 object-contain mx-auto mb-4">
                            @else
                                <div class="h-24 w-24 bg-gray-700 rounded-full mx-auto mb-4 flex items-center justify-center">
                                    <span class="text-2xl font-bold">{{ $team->short_name ?? substr($team->name, 0, 2) }}</span>
                                </div>
                            @endif
                            <h2 class="text-xl font-bold">{{ $team->name }}</h2>
                            @if($team->short_name)
                                <p class="text-gray-400">({{ $team->short_name }})</p>
                            @endif
                        </div>

                        {{-- Team Stats --}}
                        <div class="p-4 bg-gray-750">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <p class="text-2xl font-bold text-yellow-400">{{ $team->players->count() }}</p>
                                    <p class="text-xs text-gray-400">Players</p>
                                </div>
                                @php
                                    $teamEntry = $tournament->pointTableEntries->where('actual_team_id', $team->id)->first();
                                @endphp
                                <div>
                                    <p class="text-2xl font-bold text-green-400">{{ $teamEntry?->won ?? 0 }}</p>
                                    <p class="text-xs text-gray-400">Won</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-red-400">{{ $teamEntry?->lost ?? 0 }}</p>
                                    <p class="text-xs text-gray-400">Lost</p>
                                </div>
                            </div>
                        </div>

                        {{-- Players List (Collapsed) --}}
                        <div x-data="{ expanded: false }" class="border-t border-gray-700">
                            <button @click="expanded = !expanded"
                                    class="w-full px-4 py-3 flex justify-between items-center text-gray-400 hover:text-white transition">
                                <span>View Squad</span>
                                <i class="fas" :class="expanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                            </button>
                            <div x-show="expanded" x-collapse>
                                <div class="px-4 pb-4 space-y-2">
                                    @forelse($team->players as $teamPlayer)
                                        <div class="flex items-center justify-between py-2 border-b border-gray-700 last:border-0">
                                            <div class="flex items-center gap-3">
                                                @if($teamPlayer->player?->image)
                                                    <img src="{{ Storage::url($teamPlayer->player->image) }}" alt="{{ $teamPlayer->player->name }}" class="h-8 w-8 rounded-full object-cover">
                                                @else
                                                    <div class="h-8 w-8 bg-gray-700 rounded-full flex items-center justify-center">
                                                        <i class="fas fa-user text-gray-500 text-sm"></i>
                                                    </div>
                                                @endif
                                                <div>
                                                    <p class="font-medium text-sm">{{ $teamPlayer->player?->name ?? 'Unknown' }}</p>
                                                    @if($teamPlayer->is_captain)
                                                        <span class="text-xs text-yellow-400">Captain</span>
                                                    @elseif($teamPlayer->is_vice_captain)
                                                        <span class="text-xs text-gray-400">Vice Captain</span>
                                                    @endif
                                                </div>
                                            </div>
                                            @if($teamPlayer->jersey_number)
                                                <span class="text-gray-500 text-sm">#{{ $teamPlayer->jersey_number }}</span>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-gray-500 text-sm text-center py-4">No players assigned yet.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12">
                <i class="fas fa-users text-4xl text-gray-600 mb-4"></i>
                <p class="text-gray-400">Teams will be announced soon.</p>
            </div>
        @endif
    </div>
@endsection
