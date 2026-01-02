@extends('public.tournament.layouts.app')

@section('title', 'Match Poster - ' . ($match->teamA?->short_name ?? 'TBA') . ' vs ' . ($match->teamB?->short_name ?? 'TBA'))

@section('meta')
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $match->teamA?->short_name ?? 'TBA' }} vs {{ $match->teamB?->short_name ?? 'TBA' }}" />
    <meta property="og:description" content="{{ $match->match_date->format('F d, Y') }} - {{ $tournament->name }}" />
    @if($match->poster_image)
        <meta property="og:image" content="{{ Storage::url($match->poster_image) }}" />
    @endif
@endsection

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-8">
        {{-- Poster Card --}}
        <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-xl overflow-hidden border border-gray-700 shadow-2xl">
            {{-- Tournament Header --}}
            <div class="bg-gradient-to-r from-yellow-600 to-yellow-500 px-6 py-4 text-center">
                <h1 class="text-2xl font-bold text-gray-900">{{ $tournament->name }}</h1>
            </div>

            {{-- Match Info --}}
            <div class="p-8">
                {{-- Match Number & Stage --}}
                <div class="text-center mb-6">
                    @if($match->stage && $match->stage !== 'group')
                        <span class="bg-yellow-500 text-gray-900 px-4 py-1 rounded-full text-sm font-bold uppercase">
                            {{ ucwords(str_replace('_', ' ', $match->stage)) }}
                        </span>
                    @elseif($match->match_number)
                        <span class="text-gray-400">Match #{{ $match->match_number }}</span>
                    @endif
                </div>

                {{-- Teams --}}
                <div class="flex items-center justify-between mb-8">
                    {{-- Team A --}}
                    <div class="text-center flex-1">
                        @if($match->teamA?->logo)
                            <img src="{{ Storage::url($match->teamA->logo) }}" alt="{{ $match->teamA->name }}"
                                 class="h-28 w-28 object-contain mx-auto mb-4 drop-shadow-lg">
                        @else
                            <div class="h-28 w-28 bg-gray-700 rounded-full mx-auto mb-4 flex items-center justify-center shadow-lg">
                                <span class="text-3xl font-bold">{{ substr($match->teamA?->short_name ?? 'TBA', 0, 2) }}</span>
                            </div>
                        @endif
                        <h2 class="text-2xl font-bold text-white">{{ $match->teamA?->name ?? 'TBA' }}</h2>
                        @if($match->teamA?->short_name)
                            <p class="text-gray-400">({{ $match->teamA->short_name }})</p>
                        @endif
                    </div>

                    {{-- VS --}}
                    <div class="px-6">
                        <div class="w-16 h-16 rounded-full bg-yellow-500 flex items-center justify-center shadow-lg">
                            <span class="text-xl font-bold text-gray-900">VS</span>
                        </div>
                    </div>

                    {{-- Team B --}}
                    <div class="text-center flex-1">
                        @if($match->teamB?->logo)
                            <img src="{{ Storage::url($match->teamB->logo) }}" alt="{{ $match->teamB->name }}"
                                 class="h-28 w-28 object-contain mx-auto mb-4 drop-shadow-lg">
                        @else
                            <div class="h-28 w-28 bg-gray-700 rounded-full mx-auto mb-4 flex items-center justify-center shadow-lg">
                                <span class="text-3xl font-bold">{{ substr($match->teamB?->short_name ?? 'TBA', 0, 2) }}</span>
                            </div>
                        @endif
                        <h2 class="text-2xl font-bold text-white">{{ $match->teamB?->name ?? 'TBA' }}</h2>
                        @if($match->teamB?->short_name)
                            <p class="text-gray-400">({{ $match->teamB->short_name }})</p>
                        @endif
                    </div>
                </div>

                {{-- Match Details --}}
                <div class="bg-gray-800/50 rounded-xl p-6 space-y-4">
                    {{-- Date & Time --}}
                    <div class="flex items-center justify-center gap-6 text-center">
                        <div>
                            <p class="text-gray-400 text-sm">Date</p>
                            <p class="text-xl font-bold text-yellow-400">
                                {{ $match->match_date->format('l') }}
                            </p>
                            <p class="text-lg text-white">
                                {{ $match->match_date->format('F d, Y') }}
                            </p>
                        </div>
                        @if($match->match_time)
                            <div class="border-l border-gray-700 pl-6">
                                <p class="text-gray-400 text-sm">Time</p>
                                <p class="text-xl font-bold text-white">
                                    {{ $match->match_time->format('h:i A') }}
                                </p>
                            </div>
                        @endif
                    </div>

                    {{-- Venue --}}
                    @if($match->ground)
                        <div class="text-center pt-4 border-t border-gray-700">
                            <p class="text-gray-400 text-sm">Venue</p>
                            <p class="text-lg font-semibold text-white">{{ $match->ground->name }}</p>
                            @if($match->ground->address)
                                <p class="text-sm text-gray-400">{{ $match->ground->address }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Footer --}}
            <div class="bg-gray-900 px-6 py-3 text-center">
                <p class="text-gray-500 text-sm">
                    @if($tournament->settings?->overs_per_match)
                        {{ $tournament->settings->overs_per_match }} Overs Match
                    @else
                        {{ $tournament->name }}
                    @endif
                </p>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex flex-wrap gap-4 justify-center mt-8">
            <a href="{{ route('public.match.show', $match->slug) }}"
               class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition">
                <i class="fas fa-arrow-left mr-2"></i> Back to Match
            </a>
            <button onclick="shareMatch()" class="px-6 py-3 bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold rounded-lg transition">
                <i class="fas fa-share mr-2"></i> Share
            </button>
        </div>
    </div>

    @push('scripts')
    <script>
        function shareMatch() {
            if (navigator.share) {
                navigator.share({
                    title: '{{ $match->teamA?->short_name ?? "TBA" }} vs {{ $match->teamB?->short_name ?? "TBA" }}',
                    text: 'Match on {{ $match->match_date->format("F d, Y") }} - {{ $tournament->name }}',
                    url: window.location.href
                });
            } else {
                // Fallback - copy to clipboard
                navigator.clipboard.writeText(window.location.href);
                alert('Link copied to clipboard!');
            }
        }
    </script>
    @endpush
@endsection
