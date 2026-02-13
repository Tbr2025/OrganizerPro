@extends('public.tournament.layouts.app')

@section('title', 'Match Summary - ' . ($match->teamA?->short_name ?? 'TBA') . ' vs ' . ($match->teamB?->short_name ?? 'TBA'))

@section('meta')
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $result->result_summary ?? 'Match Summary' }}" />
    <meta property="og:description" content="{{ $match->teamA?->name ?? 'TBA' }} vs {{ $match->teamB?->name ?? 'TBA' }} - {{ $tournament->name }}" />
@endsection

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-8">
        {{-- Summary Card --}}
        <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-xl overflow-hidden border border-gray-700 shadow-2xl">
            {{-- Tournament Header --}}
            <div class="bg-gradient-to-r from-green-600 to-green-500 px-6 py-3 text-center">
                <p class="text-green-100 text-sm">{{ $tournament->name }}</p>
                <h1 class="text-xl font-bold text-white">Match Result</h1>
            </div>

            {{-- Score Summary --}}
            <div class="p-6">
                {{-- Team A Score --}}
                <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg mb-2 {{ $match->winner_id === $match->team_a_id ? 'border-l-4 border-green-500' : '' }}">
                    <div class="flex items-center gap-4">
                        @if($match->teamA?->logo)
                            <img src="{{ Storage::url($match->teamA->logo) }}" alt="{{ $match->teamA->name }}" class="h-12 w-12 object-contain">
                        @else
                            <div class="h-12 w-12 bg-gray-700 rounded-full flex items-center justify-center">
                                <span class="font-bold">{{ substr($match->teamA?->short_name ?? 'TBA', 0, 2) }}</span>
                            </div>
                        @endif
                        <div>
                            <h3 class="font-bold {{ $match->winner_id === $match->team_a_id ? 'text-green-400' : '' }}">
                                {{ $match->teamA?->name ?? 'TBA' }}
                            </h3>
                            @if($result->team_a_batting_first)
                                <span class="text-xs text-gray-500">Batted First</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold {{ $match->winner_id === $match->team_a_id ? 'text-green-400' : '' }}">
                            {{ $result->team_a_score }}/{{ $result->team_a_wickets }}
                        </p>
                        <p class="text-sm text-gray-400">({{ $result->team_a_overs }} ov)</p>
                    </div>
                </div>

                {{-- Team B Score --}}
                <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg {{ $match->winner_id === $match->team_b_id ? 'border-l-4 border-green-500' : '' }}">
                    <div class="flex items-center gap-4">
                        @if($match->teamB?->logo)
                            <img src="{{ Storage::url($match->teamB->logo) }}" alt="{{ $match->teamB->name }}" class="h-12 w-12 object-contain">
                        @else
                            <div class="h-12 w-12 bg-gray-700 rounded-full flex items-center justify-center">
                                <span class="font-bold">{{ substr($match->teamB?->short_name ?? 'TBA', 0, 2) }}</span>
                            </div>
                        @endif
                        <div>
                            <h3 class="font-bold {{ $match->winner_id === $match->team_b_id ? 'text-green-400' : '' }}">
                                {{ $match->teamB?->name ?? 'TBA' }}
                            </h3>
                            @if(!$result->team_a_batting_first)
                                <span class="text-xs text-gray-500">Batted First</span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold {{ $match->winner_id === $match->team_b_id ? 'text-green-400' : '' }}">
                            {{ $result->team_b_score }}/{{ $result->team_b_wickets }}
                        </p>
                        <p class="text-sm text-gray-400">({{ $result->team_b_overs }} ov)</p>
                    </div>
                </div>

                {{-- Result Summary --}}
                @if($result->result_summary)
                    <div class="mt-6 p-4 bg-yellow-500/10 border border-yellow-500/30 rounded-lg text-center">
                        <p class="text-lg font-bold text-yellow-400">{{ $result->result_summary }}</p>
                    </div>
                @endif

                {{-- Toss Info --}}
                @if($result->toss_winner_id)
                    <div class="mt-4 text-center text-sm text-gray-400">
                        <i class="fas fa-coins mr-1"></i>
                        {{ $result->toss_winner_id === $match->team_a_id ? $match->teamA?->short_name : $match->teamB?->short_name }}
                        won the toss and elected to {{ $result->toss_decision }}
                    </div>
                @endif
            </div>

            {{-- Match Awards --}}
            @if($match->matchAwards->count() > 0)
                <div class="border-t border-gray-700 p-6">
                    <h2 class="text-lg font-semibold mb-4 text-center">Match Awards</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($match->matchAwards as $award)
                            <div class="bg-gradient-to-r from-yellow-600/20 to-yellow-500/10 rounded-lg p-4 flex items-center gap-4 border border-yellow-500/30">
                                @if($award->player?->image)
                                    <img src="{{ Storage::url($award->player->image) }}" alt="{{ $award->player->name }}" class="h-14 w-14 rounded-full object-cover border-2 border-yellow-500">
                                @else
                                    <div class="h-14 w-14 bg-gray-700 rounded-full flex items-center justify-center border-2 border-yellow-500">
                                        <i class="fas fa-trophy text-yellow-500"></i>
                                    </div>
                                @endif
                                <div>
                                    <p class="text-yellow-400 text-sm font-medium">{{ $award->tournamentAward?->name ?? 'Award' }}</p>
                                    <p class="font-bold text-white">{{ $award->player?->name ?? 'Unknown' }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Match Details --}}
            <div class="bg-gray-900 px-6 py-4 flex flex-wrap justify-center gap-6 text-sm text-gray-400">
                <span>
                    <i class="far fa-calendar mr-1"></i>
                    {{ $match->match_date->format('M d, Y') }}
                </span>
                @if($match->ground)
                    <span>
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        {{ $match->ground->name }}
                    </span>
                @endif
                @if($match->match_number)
                    <span>Match #{{ $match->match_number }}</span>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex flex-wrap gap-4 justify-center mt-8">
            <a href="{{ route('public.match.show', $match->slug) }}"
               class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition">
                <i class="fas fa-arrow-left mr-2"></i> Back to Match
            </a>
            <a href="{{ route('public.match.scorecard', $match->slug) }}"
               class="px-6 py-3 bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold rounded-lg transition">
                View Scorecard
            </a>
        </div>

        {{-- Share Section --}}
        <div class="mt-8 text-center">
            <h3 class="text-sm text-gray-400 mb-4">Share this result</h3>
            @php
                $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                $shareMessage = $whatsappService->getResultShareLink($match);
            @endphp
            <x-share-buttons
                :title="$result->result_summary ?? 'Match Result'"
                :description="($match->teamA?->name ?? 'TBA') . ' vs ' . ($match->teamB?->name ?? 'TBA') . ' - ' . $tournament->name"
                :whatsappMessage="$whatsappService->getResultShareMessage($match)"
                variant="compact"
                :showLabel="false"
                class="justify-center"
            />
        </div>
    </div>
@endsection
