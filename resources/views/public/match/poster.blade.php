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

        @if($match->poster_image && Storage::disk('public')->exists($match->poster_image))
            {{-- Actual generated poster image --}}
            <div class="text-center">
                <img src="{{ Storage::url($match->poster_image) }}" alt="{{ $match->teamA?->short_name ?? 'TBA' }} vs {{ $match->teamB?->short_name ?? 'TBA' }}"
                     class="w-full rounded-xl shadow-2xl border border-gray-700">
            </div>
        @else
            {{-- Match poster (HTML) --}}
            <div class="poster-card relative rounded-2xl overflow-hidden shadow-2xl" style="aspect-ratio:1/1; background: linear-gradient(160deg, var(--primary) 0%, var(--secondary) 50%, var(--primary) 100%);">
                {{-- Background pattern --}}
                <div class="absolute inset-0 opacity-[0.04]" style="background-image:radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size:24px 24px;"></div>
                {{-- Accent glow behind VS --}}
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 rounded-full blur-[100px] opacity-20" style="background:var(--accent);"></div>

                <div class="relative flex flex-col h-full p-6 sm:p-8">
                    {{-- Top: Tournament branding + match badge --}}
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            @if($tournament->settings?->logo)
                                <img src="{{ Storage::url($tournament->settings->logo) }}" alt="" class="h-10 w-10 object-contain rounded-lg">
                            @endif
                            <div>
                                <p class="text-sm font-bold text-white leading-tight">{{ $tournament->name }}</p>
                                @if($tournament->settings?->overs_per_match)
                                    <p class="text-[11px] text-gray-400">{{ $tournament->settings->overs_per_match }} Overs</p>
                                @endif
                            </div>
                        </div>
                        @if($match->match_number || ($match->stage && $match->stage !== 'group'))
                            <span class="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider border"
                                  style="color:var(--accent); border-color:rgba(var(--accent-rgb),0.3); background:rgba(var(--accent-rgb),0.1);">
                                @if($match->stage && $match->stage !== 'group')
                                    {{ ucwords(str_replace('_', ' ', $match->stage)) }}
                                @else
                                    Match {{ $match->match_number }}
                                @endif
                            </span>
                        @endif
                    </div>

                    {{-- Center: Teams vs Teams (grows to fill) --}}
                    <div class="flex-1 flex items-center justify-center">
                        <div class="flex items-center gap-4 sm:gap-8 w-full max-w-lg">
                            {{-- Team A --}}
                            <div class="flex-1 text-center">
                                @if($match->teamA?->team_logo)
                                    <img src="{{ Storage::url($match->teamA->team_logo) }}" alt="{{ $match->teamA->name }}"
                                         class="w-24 h-24 sm:w-32 sm:h-32 object-contain mx-auto mb-3 drop-shadow-[0_4px_20px_rgba(0,0,0,0.4)]">
                                @else
                                    <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-full mx-auto mb-3 flex items-center justify-center border-2 border-white/10"
                                         style="background:rgba(var(--accent-rgb),0.15);">
                                        <span class="text-3xl sm:text-4xl font-black" style="color:var(--accent);">{{ strtoupper(substr($match->teamA?->short_name ?? $match->teamA?->name ?? 'TBA', 0, 3)) }}</span>
                                    </div>
                                @endif
                                <h2 class="text-lg sm:text-xl font-extrabold text-white leading-tight">{{ $match->teamA?->short_name ?? $match->teamA?->name ?? 'TBA' }}</h2>
                            </div>

                            {{-- VS --}}
                            <div class="shrink-0">
                                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full flex items-center justify-center shadow-lg"
                                     style="background:linear-gradient(135deg, var(--accent), var(--accent-dark)); box-shadow:0 0 30px rgba(var(--accent-rgb),0.3);">
                                    <span class="text-lg sm:text-xl font-black text-gray-900">VS</span>
                                </div>
                            </div>

                            {{-- Team B --}}
                            <div class="flex-1 text-center">
                                @if($match->teamB?->team_logo)
                                    <img src="{{ Storage::url($match->teamB->team_logo) }}" alt="{{ $match->teamB->name }}"
                                         class="w-24 h-24 sm:w-32 sm:h-32 object-contain mx-auto mb-3 drop-shadow-[0_4px_20px_rgba(0,0,0,0.4)]">
                                @else
                                    <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-full mx-auto mb-3 flex items-center justify-center border-2 border-white/10"
                                         style="background:rgba(var(--accent-rgb),0.15);">
                                        <span class="text-3xl sm:text-4xl font-black" style="color:var(--accent);">{{ strtoupper(substr($match->teamB?->short_name ?? $match->teamB?->name ?? 'TBA', 0, 3)) }}</span>
                                    </div>
                                @endif
                                <h2 class="text-lg sm:text-xl font-extrabold text-white leading-tight">{{ $match->teamB?->short_name ?? $match->teamB?->name ?? 'TBA' }}</h2>
                            </div>
                        </div>
                    </div>

                    {{-- Bottom: Match details --}}
                    <div class="rounded-xl p-4 sm:p-5 mt-4" style="background:rgba(0,0,0,0.3); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,0.06);">
                        <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-center">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-gray-500 mb-0.5">Date</p>
                                <p class="text-sm font-bold" style="color:var(--accent);">{{ $match->match_date->format('D, M d Y') }}</p>
                            </div>
                            @if($match->start_time)
                                <div class="w-px h-8 bg-white/10 hidden sm:block"></div>
                                <div>
                                    <p class="text-[10px] uppercase tracking-wider text-gray-500 mb-0.5">Time</p>
                                    <p class="text-sm font-bold text-white">{{ \Carbon\Carbon::parse($match->start_time)->format('h:i A') }}</p>
                                </div>
                            @endif
                            @if($match->ground)
                                <div class="w-px h-8 bg-white/10 hidden sm:block"></div>
                                <div>
                                    <p class="text-[10px] uppercase tracking-wider text-gray-500 mb-0.5">Venue</p>
                                    <p class="text-sm font-bold text-white">{{ $match->ground->name }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

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
