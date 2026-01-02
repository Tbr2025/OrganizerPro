@extends('public.tournament.layouts.app')

@section('title', ($match->teamA?->short_name ?? 'TBA') . ' vs ' . ($match->teamB?->short_name ?? 'TBA') . ' - ' . $tournament->name)

@section('meta')
    <meta name="description" content="{{ $match->teamA?->name ?? 'TBA' }} vs {{ $match->teamB?->name ?? 'TBA' }} - {{ $tournament->name }}">
    <meta property="og:title" content="{{ $match->teamA?->short_name ?? 'TBA' }} vs {{ $match->teamB?->short_name ?? 'TBA' }}" />
    <meta property="og:description" content="Match #{{ $match->match_number }} - {{ $tournament->name }}" />
@endsection

@section('content')
    <div class="max-w-4xl mx-auto px-4 py-8">
        {{-- Match Header --}}
        <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700 mb-6">
            {{-- Match Info Bar --}}
            <div class="bg-gray-700 px-4 py-2 flex flex-wrap justify-between items-center text-sm">
                <div class="flex items-center gap-2">
                    @if($match->match_number)
                        <span class="bg-gray-600 px-2 py-1 rounded">Match #{{ $match->match_number }}</span>
                    @endif
                    @if($match->stage)
                        <span class="bg-yellow-600 text-yellow-100 px-2 py-1 rounded">
                            {{ ucwords(str_replace('_', ' ', $match->stage)) }}
                        </span>
                    @endif
                    @if($match->group)
                        <span class="bg-blue-600 text-blue-100 px-2 py-1 rounded">
                            {{ $match->group->name }}
                        </span>
                    @endif
                </div>
                <div class="text-gray-400">
                    @if($match->status === 'live')
                        <span class="bg-red-600 text-white px-3 py-1 rounded animate-pulse">LIVE</span>
                    @elseif($match->status === 'completed')
                        <span class="text-green-400">Completed</span>
                    @else
                        {{ $match->match_date->format('D, M d, Y') }}
                        @if($match->match_time)
                            at {{ $match->match_time->format('h:i A') }}
                        @endif
                    @endif
                </div>
            </div>

            {{-- Teams & Score --}}
            <div class="p-6">
                <div class="flex items-center justify-between">
                    {{-- Team A --}}
                    <div class="text-center flex-1">
                        @if($match->teamA?->logo)
                            <img src="{{ Storage::url($match->teamA->logo) }}" alt="{{ $match->teamA->name }}" class="h-20 w-20 object-contain mx-auto mb-3">
                        @else
                            <div class="h-20 w-20 bg-gray-700 rounded-full mx-auto mb-3 flex items-center justify-center">
                                <span class="text-2xl font-bold">{{ substr($match->teamA?->short_name ?? 'TBA', 0, 2) }}</span>
                            </div>
                        @endif
                        <h2 class="text-xl font-bold {{ $match->winner_id === $match->team_a_id ? 'text-green-400' : '' }}">
                            {{ $match->teamA?->name ?? 'TBA' }}
                        </h2>
                        @if($match->result)
                            <p class="text-3xl font-bold mt-2 {{ $match->winner_id === $match->team_a_id ? 'text-green-400' : 'text-gray-400' }}">
                                {{ $match->result->team_a_score }}/{{ $match->result->team_a_wickets }}
                            </p>
                            <p class="text-sm text-gray-500">({{ $match->result->team_a_overs }} ov)</p>
                        @endif
                    </div>

                    {{-- VS --}}
                    <div class="px-6 text-center">
                        <span class="text-2xl text-gray-500 font-bold">VS</span>
                    </div>

                    {{-- Team B --}}
                    <div class="text-center flex-1">
                        @if($match->teamB?->logo)
                            <img src="{{ Storage::url($match->teamB->logo) }}" alt="{{ $match->teamB->name }}" class="h-20 w-20 object-contain mx-auto mb-3">
                        @else
                            <div class="h-20 w-20 bg-gray-700 rounded-full mx-auto mb-3 flex items-center justify-center">
                                <span class="text-2xl font-bold">{{ substr($match->teamB?->short_name ?? 'TBA', 0, 2) }}</span>
                            </div>
                        @endif
                        <h2 class="text-xl font-bold {{ $match->winner_id === $match->team_b_id ? 'text-green-400' : '' }}">
                            {{ $match->teamB?->name ?? 'TBA' }}
                        </h2>
                        @if($match->result)
                            <p class="text-3xl font-bold mt-2 {{ $match->winner_id === $match->team_b_id ? 'text-green-400' : 'text-gray-400' }}">
                                {{ $match->result->team_b_score }}/{{ $match->result->team_b_wickets }}
                            </p>
                            <p class="text-sm text-gray-500">({{ $match->result->team_b_overs }} ov)</p>
                        @endif
                    </div>
                </div>

                {{-- Result Summary --}}
                @if($match->result?->result_summary)
                    <div class="mt-6 text-center">
                        <p class="text-lg text-yellow-400 font-medium">{{ $match->result->result_summary }}</p>
                    </div>
                @endif
            </div>

            {{-- Match Details --}}
            <div class="bg-gray-750 px-6 py-4 border-t border-gray-700">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    @if($match->ground)
                        <div class="flex items-center gap-2 text-gray-400">
                            <i class="fas fa-map-marker-alt text-yellow-400"></i>
                            <span>{{ $match->ground->name }}</span>
                        </div>
                    @endif
                    @if($match->result?->toss_winner_id)
                        <div class="flex items-center gap-2 text-gray-400">
                            <i class="fas fa-coins text-yellow-400"></i>
                            <span>
                                {{ $match->result->toss_winner_id === $match->team_a_id ? $match->teamA?->short_name : $match->teamB?->short_name }}
                                won toss, elected to {{ $match->result->toss_decision }}
                            </span>
                        </div>
                    @endif
                    @if($tournament->settings?->overs_per_match)
                        <div class="flex items-center gap-2 text-gray-400">
                            <i class="fas fa-cricket text-yellow-400"></i>
                            <span>{{ $tournament->settings->overs_per_match }} Overs Match</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Match Awards --}}
        @if($match->matchAwards->count() > 0)
            <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700 mb-6">
                <div class="p-4 bg-gray-700 border-b border-gray-600">
                    <h2 class="text-lg font-semibold">Match Awards</h2>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($match->matchAwards as $award)
                            <div class="bg-gray-700 rounded-lg p-4 flex items-center gap-4">
                                @if($award->player?->image)
                                    <img src="{{ Storage::url($award->player->image) }}" alt="{{ $award->player->name }}" class="h-16 w-16 rounded-full object-cover">
                                @else
                                    <div class="h-16 w-16 bg-gray-600 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-2xl text-gray-500"></i>
                                    </div>
                                @endif
                                <div>
                                    <p class="text-yellow-400 text-sm font-medium">{{ $award->tournamentAward?->name ?? 'Award' }}</p>
                                    <p class="font-bold">{{ $award->player?->name ?? 'Unknown' }}</p>
                                    @if($award->description)
                                        <p class="text-sm text-gray-400">{{ $award->description }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Appreciation Images --}}
        @if($match->appreciations->count() > 0)
            <div class="bg-gray-800 rounded-xl overflow-hidden border border-gray-700 mb-6">
                <div class="p-4 bg-gray-700 border-b border-gray-600">
                    <h2 class="text-lg font-semibold">Match Gallery</h2>
                </div>
                <div class="p-4">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach($match->appreciations as $appreciation)
                            @if($appreciation->image_path)
                                <a href="{{ Storage::url($appreciation->image_path) }}" target="_blank" class="block">
                                    <img src="{{ Storage::url($appreciation->image_path) }}" alt="Match Appreciation" class="w-full rounded-lg hover:opacity-90 transition">
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Action Buttons --}}
        <div class="flex flex-wrap gap-4 justify-center">
            @if($match->result)
                <a href="{{ route('public.match.scorecard', $match->slug) }}"
                   class="px-6 py-3 bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold rounded-lg transition">
                    View Scorecard
                </a>
            @endif
            <a href="{{ route('public.match.poster', $match->slug) }}"
               class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition">
                Match Poster
            </a>
            @if($match->result)
                <a href="{{ route('public.match.summary', $match->slug) }}"
                   class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition">
                    Match Summary
                </a>
            @endif
        </div>

        {{-- Other Matches --}}
        @if($otherMatches->count() > 0)
            <div class="mt-12">
                <h2 class="text-xl font-bold mb-4">Other Matches</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($otherMatches as $otherMatch)
                        <a href="{{ route('public.match.show', $otherMatch->slug) }}"
                           class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition border border-gray-700">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    @if($otherMatch->teamA?->logo)
                                        <img src="{{ Storage::url($otherMatch->teamA->logo) }}" alt="{{ $otherMatch->teamA->name }}" class="h-8 w-8 object-contain">
                                    @endif
                                    <span class="text-sm">{{ $otherMatch->teamA?->short_name ?? 'TBA' }}</span>
                                </div>
                                <span class="text-gray-500 text-xs">vs</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm">{{ $otherMatch->teamB?->short_name ?? 'TBA' }}</span>
                                    @if($otherMatch->teamB?->logo)
                                        <img src="{{ Storage::url($otherMatch->teamB->logo) }}" alt="{{ $otherMatch->teamB->name }}" class="h-8 w-8 object-contain">
                                    @endif
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2 text-center">
                                {{ $otherMatch->match_date->format('M d') }}
                            </p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection
