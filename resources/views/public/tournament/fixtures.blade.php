@extends('public.tournament.layouts.app')

@section('title', 'Fixtures - ' . $tournament->name)

@push('styles')
<style>
    .page-header {
        background: linear-gradient(135deg, #0f0f23 0%, #1a1a3e 50%, #0d1b2a 100%);
    }
    .filter-btn {
        transition: all 0.3s ease;
    }
    .filter-btn.active {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: #1f2937;
        box-shadow: 0 4px 15px rgba(251, 191, 36, 0.4);
    }
    .match-card {
        background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
        transition: all 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .match-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        border-color: rgba(251, 191, 36, 0.3);
    }
    .date-header {
        background: linear-gradient(90deg, rgba(251, 191, 36, 0.2) 0%, transparent 100%);
        border-left: 4px solid #fbbf24;
    }
    .team-logo-container {
        background: linear-gradient(145deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.02) 100%);
    }
    .vs-badge {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    }
    .live-badge {
        animation: pulse-live 1.5s ease-in-out infinite;
    }
    @keyframes pulse-live {
        0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        50% { opacity: 0.8; box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
    }
    .stage-badge {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.3) 0%, rgba(139, 92, 246, 0.1) 100%);
        border: 1px solid rgba(139, 92, 246, 0.5);
    }
    .group-badge {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.3) 0%, rgba(59, 130, 246, 0.1) 100%);
        border: 1px solid rgba(59, 130, 246, 0.5);
    }
    .winner-glow {
        text-shadow: 0 0 10px rgba(34, 197, 94, 0.5);
    }
</style>
@endpush

@section('content')
    {{-- Page Header --}}
    <section class="page-header py-16 relative overflow-hidden">
        <div class="absolute inset-0 opacity-30">
            <div class="absolute top-0 left-1/4 w-96 h-96 bg-yellow-500/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl"></div>
        </div>
        <div class="relative max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <span class="inline-block px-4 py-2 bg-blue-500/20 text-blue-400 rounded-full text-sm font-semibold mb-4">
                        <i class="fas fa-calendar-alt mr-2"></i>Match Schedule
                    </span>
                    <h1 class="text-4xl md:text-5xl font-bold text-white">Fixtures & Results</h1>
                </div>
                <div>
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
        </div>
    </section>

    {{-- Filters --}}
    <section class="py-8 bg-gray-900 sticky top-16 z-40 border-b border-gray-800">
        <div class="max-w-6xl mx-auto px-4">
            <form method="GET" class="flex flex-wrap gap-4 items-center justify-center">
                {{-- Stage Filter --}}
                <div class="relative">
                    <select name="stage" onchange="this.form.submit()"
                            class="appearance-none bg-gray-800 border border-gray-700 rounded-xl px-6 py-3 pr-10 text-white font-medium focus:ring-2 focus:ring-yellow-500 focus:border-transparent cursor-pointer">
                        <option value="">All Stages</option>
                        <option value="group" {{ $selectedStage === 'group' ? 'selected' : '' }}>Group Stage</option>
                        <option value="quarter_final" {{ $selectedStage === 'quarter_final' ? 'selected' : '' }}>Quarter Finals</option>
                        <option value="semi_final" {{ $selectedStage === 'semi_final' ? 'selected' : '' }}>Semi Finals</option>
                        <option value="final" {{ $selectedStage === 'final' ? 'selected' : '' }}>Final</option>
                    </select>
                    <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                </div>

                {{-- Group Filter --}}
                @if($groups->count() > 0)
                    <div class="relative">
                        <select name="group_id" onchange="this.form.submit()"
                                class="appearance-none bg-gray-800 border border-gray-700 rounded-xl px-6 py-3 pr-10 text-white font-medium focus:ring-2 focus:ring-yellow-500 focus:border-transparent cursor-pointer">
                            <option value="">All Groups</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" {{ $selectedGroupId == $group->id ? 'selected' : '' }}>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                    </div>
                @endif

                {{-- Quick Stats --}}
                <div class="hidden md:flex items-center gap-6 ml-auto text-sm">
                    <div class="flex items-center gap-2 text-gray-400">
                        <span class="w-3 h-3 rounded-full bg-green-500"></span>
                        <span>{{ $matches->where('status', 'completed')->count() }} Completed</span>
                    </div>
                    <div class="flex items-center gap-2 text-gray-400">
                        <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
                        <span>{{ $matches->where('status', 'upcoming')->count() }} Upcoming</span>
                    </div>
                </div>
            </form>
        </div>
    </section>

    {{-- Matches by Date --}}
    <section class="py-12 bg-gray-900 min-h-screen">
        <div class="max-w-6xl mx-auto px-4">
            @forelse($matchesByDate as $date => $dayMatches)
                {{-- Date Header --}}
                <div class="date-header rounded-r-xl px-6 py-4 mb-6">
                    <h2 class="text-xl md:text-2xl font-bold text-white flex items-center gap-3">
                        <i class="far fa-calendar text-yellow-400"></i>
                        {{ \Carbon\Carbon::parse($date)->format('l, F d, Y') }}
                        <span class="text-sm font-normal text-gray-400 ml-2">
                            ({{ $dayMatches->count() }} {{ Str::plural('match', $dayMatches->count()) }})
                        </span>
                    </h2>
                </div>

                {{-- Matches --}}
                <div class="space-y-4 mb-12">
                    @foreach($dayMatches as $match)
                        <a href="{{ route('public.match.show', $match->slug) }}" class="block match-card rounded-2xl overflow-hidden">
                            {{-- Match Header --}}
                            <div class="bg-gray-800/50 px-6 py-3 flex flex-wrap items-center gap-3">
                                @if($match->match_number)
                                    <span class="text-sm font-semibold text-gray-300 bg-gray-700 px-3 py-1 rounded-lg">
                                        Match #{{ $match->match_number }}
                                    </span>
                                @endif
                                @if($match->stage)
                                    <span class="stage-badge text-purple-300 text-xs font-semibold px-3 py-1 rounded-lg uppercase tracking-wide">
                                        {{ ucwords(str_replace('_', ' ', $match->stage)) }}
                                    </span>
                                @endif
                                @if($match->group)
                                    <span class="group-badge text-blue-300 text-xs font-semibold px-3 py-1 rounded-lg">
                                        {{ $match->group->name }}
                                    </span>
                                @endif

                                <div class="ml-auto flex items-center gap-3">
                                    @if($match->status === 'live')
                                        <span class="live-badge bg-red-600 text-white text-xs font-bold px-3 py-1 rounded-full flex items-center gap-2">
                                            <span class="w-2 h-2 bg-white rounded-full"></span>
                                            LIVE
                                        </span>
                                    @elseif($match->status === 'completed')
                                        <span class="bg-green-600/30 text-green-400 text-xs font-semibold px-3 py-1 rounded-lg">
                                            <i class="fas fa-check-circle mr-1"></i>Completed
                                        </span>
                                    @elseif($match->match_time)
                                        <span class="text-yellow-400 text-sm font-semibold">
                                            <i class="far fa-clock mr-1"></i>{{ $match->match_time->format('h:i A') }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Match Content --}}
                            <div class="p-6">
                                <div class="flex items-center justify-between gap-4">
                                    {{-- Team A --}}
                                    <div class="flex items-center gap-4 flex-1">
                                        <div class="team-logo-container w-16 h-16 rounded-full flex items-center justify-center overflow-hidden flex-shrink-0">
                                            @if($match->teamA?->logo)
                                                <img src="{{ Storage::url($match->teamA->logo) }}" alt="{{ $match->teamA->name }}" class="w-12 h-12 object-contain">
                                            @else
                                                <span class="text-xl font-bold text-gray-400">{{ substr($match->teamA?->short_name ?? 'TBA', 0, 2) }}</span>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="font-bold text-lg {{ $match->winner_team_id === $match->team_a_id ? 'text-green-400 winner-glow' : 'text-white' }}">
                                                {{ $match->teamA?->name ?? 'TBA' }}
                                                @if($match->winner_team_id === $match->team_a_id)
                                                    <i class="fas fa-trophy text-yellow-400 ml-2 text-sm"></i>
                                                @endif
                                            </p>
                                            @if($match->result)
                                                <p class="text-2xl font-black text-white mt-1">
                                                    {{ $match->result->team_a_score }}/{{ $match->result->team_a_wickets }}
                                                    <span class="text-sm text-gray-400 font-normal">({{ $match->result->team_a_overs }} ov)</span>
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- VS / Time --}}
                                    <div class="flex-shrink-0 text-center px-4">
                                        @if($match->status === 'completed')
                                            <span class="text-gray-500 text-lg font-semibold">vs</span>
                                        @elseif($match->status === 'live')
                                            <div class="vs-badge w-14 h-14 rounded-full flex items-center justify-center">
                                                <span class="text-sm font-black text-gray-900">LIVE</span>
                                            </div>
                                        @else
                                            <div class="vs-badge w-14 h-14 rounded-full flex items-center justify-center">
                                                <span class="text-sm font-black text-gray-900">VS</span>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Team B --}}
                                    <div class="flex items-center gap-4 flex-1 flex-row-reverse text-right">
                                        <div class="team-logo-container w-16 h-16 rounded-full flex items-center justify-center overflow-hidden flex-shrink-0">
                                            @if($match->teamB?->logo)
                                                <img src="{{ Storage::url($match->teamB->logo) }}" alt="{{ $match->teamB->name }}" class="w-12 h-12 object-contain">
                                            @else
                                                <span class="text-xl font-bold text-gray-400">{{ substr($match->teamB?->short_name ?? 'TBA', 0, 2) }}</span>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="font-bold text-lg {{ $match->winner_team_id === $match->team_b_id ? 'text-green-400 winner-glow' : 'text-white' }}">
                                                {{ $match->teamB?->name ?? 'TBA' }}
                                                @if($match->winner_team_id === $match->team_b_id)
                                                    <i class="fas fa-trophy text-yellow-400 ml-2 text-sm"></i>
                                                @endif
                                            </p>
                                            @if($match->result)
                                                <p class="text-2xl font-black text-white mt-1">
                                                    {{ $match->result->team_b_score }}/{{ $match->result->team_b_wickets }}
                                                    <span class="text-sm text-gray-400 font-normal">({{ $match->result->team_b_overs }} ov)</span>
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Result Summary & Venue --}}
                                <div class="mt-4 pt-4 border-t border-gray-700/50 flex flex-wrap items-center justify-between gap-3">
                                    @if($match->result?->result_summary)
                                        <span class="inline-flex items-center px-4 py-2 bg-yellow-500/20 text-yellow-400 rounded-full text-sm font-semibold">
                                            <i class="fas fa-star mr-2"></i>
                                            {{ $match->result->result_summary }}
                                        </span>
                                    @endif
                                    @if($match->ground)
                                        <span class="text-sm text-gray-400 ml-auto">
                                            <i class="fas fa-map-marker-alt mr-2 text-red-400"></i>
                                            {{ $match->ground->name }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @empty
                <div class="text-center py-20">
                    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gray-800 flex items-center justify-center">
                        <i class="fas fa-calendar-times text-4xl text-gray-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-2">No Fixtures Yet</h3>
                    <p class="text-gray-400">Match fixtures will be available soon.</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
