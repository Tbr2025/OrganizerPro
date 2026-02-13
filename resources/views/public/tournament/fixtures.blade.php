@extends('public.tournament.layouts.app')

@section('title', 'Fixtures - ' . $tournament->name)

@section('content')
    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <h1 class="text-3xl font-bold text-center md:text-left">Fixtures & Results</h1>
            <div class="mt-4 md:mt-0">
                @php
                    $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                    $shareMessage = "Fixtures & Results - {$tournament->name}\n\n" . request()->url();
                @endphp
                <x-share-buttons
                    :title="'Fixtures - ' . $tournament->name"
                    :description="$tournament->name . ' match schedule'"
                    :whatsappMessage="$shareMessage"
                    variant="compact"
                    :showLabel="false"
                />
            </div>
        </div>

        {{-- Filters --}}
        <div class="bg-gray-800 rounded-xl p-4 mb-8">
            <form method="GET" class="flex flex-wrap gap-4 items-center justify-center">
                {{-- Stage Filter --}}
                <select name="stage" onchange="this.form.submit()"
                        class="bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                    <option value="">All Stages</option>
                    <option value="group" {{ $selectedStage === 'group' ? 'selected' : '' }}>Group Stage</option>
                    <option value="quarter_final" {{ $selectedStage === 'quarter_final' ? 'selected' : '' }}>Quarter Finals</option>
                    <option value="semi_final" {{ $selectedStage === 'semi_final' ? 'selected' : '' }}>Semi Finals</option>
                    <option value="final" {{ $selectedStage === 'final' ? 'selected' : '' }}>Final</option>
                </select>

                {{-- Group Filter --}}
                @if($groups->count() > 0)
                    <select name="group_id" onchange="this.form.submit()"
                            class="bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                        <option value="">All Groups</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}" {{ $selectedGroupId == $group->id ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </form>
        </div>

        {{-- Matches by Date --}}
        @forelse($matchesByDate as $date => $dayMatches)
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4 text-yellow-400">
                    {{ \Carbon\Carbon::parse($date)->format('l, F d, Y') }}
                </h2>
                <div class="space-y-4">
                    @foreach($dayMatches as $match)
                        <a href="{{ route('public.match.show', $match->slug) }}"
                           class="block bg-gray-800 rounded-xl p-4 hover:bg-gray-700 transition border border-gray-700 hover:border-yellow-500">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                {{-- Match Info --}}
                                <div class="flex items-center gap-2 text-sm text-gray-400">
                                    @if($match->match_number)
                                        <span class="bg-gray-700 px-2 py-1 rounded">Match #{{ $match->match_number }}</span>
                                    @endif
                                    @if($match->stage)
                                        <span class="bg-yellow-600 text-yellow-100 px-2 py-1 rounded text-xs">
                                            {{ ucwords(str_replace('_', ' ', $match->stage)) }}
                                        </span>
                                    @endif
                                    @if($match->group)
                                        <span class="bg-blue-600 text-blue-100 px-2 py-1 rounded text-xs">
                                            {{ $match->group->name }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Teams & Score --}}
                                <div class="flex items-center justify-between flex-1">
                                    <div class="flex items-center gap-3 flex-1">
                                        @if($match->teamA?->logo)
                                            <img src="{{ Storage::url($match->teamA->logo) }}" alt="{{ $match->teamA->name }}" class="h-10 w-10 object-contain">
                                        @else
                                            <div class="h-10 w-10 bg-gray-700 rounded-full flex items-center justify-center">
                                                <span class="text-sm font-bold">{{ substr($match->teamA?->short_name ?? 'TBA', 0, 2) }}</span>
                                            </div>
                                        @endif
                                        <div>
                                            <p class="font-medium {{ $match->winner_id === $match->team_a_id ? 'text-green-400' : '' }}">
                                                {{ $match->teamA?->name ?? 'TBA' }}
                                            </p>
                                            @if($match->result)
                                                <p class="text-sm text-gray-400">
                                                    {{ $match->result->team_a_score }}/{{ $match->result->team_a_wickets }}
                                                    ({{ $match->result->team_a_overs }} ov)
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="px-4">
                                        @if($match->status === 'completed')
                                            <span class="text-gray-500 text-sm">vs</span>
                                        @elseif($match->status === 'live')
                                            <span class="bg-red-600 text-white px-2 py-1 rounded text-xs animate-pulse">LIVE</span>
                                        @else
                                            <span class="text-gray-500">
                                                @if($match->match_time)
                                                    {{ $match->match_time->format('h:i A') }}
                                                @else
                                                    vs
                                                @endif
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-3 flex-1 justify-end text-right">
                                        <div>
                                            <p class="font-medium {{ $match->winner_id === $match->team_b_id ? 'text-green-400' : '' }}">
                                                {{ $match->teamB?->name ?? 'TBA' }}
                                            </p>
                                            @if($match->result)
                                                <p class="text-sm text-gray-400">
                                                    {{ $match->result->team_b_score }}/{{ $match->result->team_b_wickets }}
                                                    ({{ $match->result->team_b_overs }} ov)
                                                </p>
                                            @endif
                                        </div>
                                        @if($match->teamB?->logo)
                                            <img src="{{ Storage::url($match->teamB->logo) }}" alt="{{ $match->teamB->name }}" class="h-10 w-10 object-contain">
                                        @else
                                            <div class="h-10 w-10 bg-gray-700 rounded-full flex items-center justify-center">
                                                <span class="text-sm font-bold">{{ substr($match->teamB?->short_name ?? 'TBA', 0, 2) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Venue --}}
                                @if($match->ground)
                                    <div class="text-sm text-gray-500">
                                        <i class="fas fa-map-marker-alt mr-1"></i> {{ $match->ground->name }}
                                    </div>
                                @endif
                            </div>

                            {{-- Result Summary --}}
                            @if($match->result?->result_summary)
                                <p class="text-sm text-yellow-400 mt-3 text-center border-t border-gray-700 pt-3">
                                    {{ $match->result->result_summary }}
                                </p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="text-center py-12">
                <i class="fas fa-calendar-times text-4xl text-gray-600 mb-4"></i>
                <p class="text-gray-400">No fixtures available yet.</p>
            </div>
        @endforelse
    </div>
@endsection
