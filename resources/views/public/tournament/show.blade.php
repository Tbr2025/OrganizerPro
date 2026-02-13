@extends('public.tournament.layouts.app')

@section('title', $tournament->name)

@section('meta')
    <meta name="description" content="{{ $tournament->description ?? 'Cricket Tournament - ' . $tournament->name }}">
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ route('public.tournament.show', $tournament->slug) }}" />
    <meta property="og:title" content="{{ $tournament->name }}" />
    <meta property="og:description" content="{{ $tournament->description ?? 'Cricket Tournament' }}" />
    @if($settings?->logo)
        <meta property="og:image" content="{{ Storage::url($settings->logo) }}" />
    @endif
@endsection

@section('content')
    {{-- Hero Section --}}
    <section class="relative h-96 md:h-screen max-h-[600px] overflow-hidden">
        @if($settings?->background_image)
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ Storage::url($settings->background_image) }}');"></div>
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-gray-800 to-gray-900"></div>
        @endif
        <div class="absolute inset-0 gradient-overlay"></div>

        <div class="relative h-full flex flex-col items-center justify-center text-center px-4">
            @if($settings?->logo)
                <img src="{{ Storage::url($settings->logo) }}" alt="{{ $tournament->name }}" class="h-32 md:h-48 w-auto object-contain mb-6">
            @endif
            <h1 class="text-4xl md:text-6xl font-bold text-white mb-4">{{ $tournament->name }}</h1>
            @if($tournament->description)
                <p class="text-lg md:text-xl text-gray-300 max-w-2xl">{{ $tournament->description }}</p>
            @endif

            {{-- Status Badge --}}
            <div class="mt-6">
                @if($tournament->status === 'registration')
                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-green-600 text-white">
                        <span class="w-2 h-2 bg-white rounded-full mr-2 animate-pulse"></span>
                        Registration Open
                    </span>
                @elseif($tournament->status === 'active')
                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-yellow-600 text-white">
                        <span class="w-2 h-2 bg-white rounded-full mr-2 animate-pulse"></span>
                        Tournament Live
                    </span>
                @elseif($tournament->status === 'completed')
                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-gray-600 text-white">
                        Completed
                    </span>
                @endif
            </div>

            {{-- Registration Buttons --}}
            @if($tournament->status === 'registration')
                <div class="mt-8 flex flex-wrap gap-4 justify-center">
                    @if($settings?->player_registration_enabled)
                        <a href="{{ route('public.tournament.register.player', $tournament->slug) }}"
                           class="px-6 py-3 bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold rounded-lg transition transform hover:scale-105">
                            Register as Player
                        </a>
                    @endif
                    @if($settings?->team_registration_enabled)
                        <a href="{{ route('public.tournament.register.team', $tournament->slug) }}"
                           class="px-6 py-3 bg-white hover:bg-gray-100 text-gray-900 font-bold rounded-lg transition transform hover:scale-105">
                            Register Team
                        </a>
                    @endif
                </div>
            @endif

            {{-- Share Buttons --}}
            <div class="mt-6">
                @php
                    $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                    $shareMessage = $whatsappService->getTournamentShareMessage($tournament);
                @endphp
                <x-share-buttons
                    :title="$tournament->name"
                    :description="$tournament->description ?? 'Cricket Tournament'"
                    :whatsappMessage="$shareMessage"
                    variant="compact"
                    :showLabel="false"
                    class="justify-center"
                />
            </div>
        </div>
    </section>

    {{-- Champion Section (if completed) --}}
    @if($tournament->status === 'completed' && $tournament->champion)
        <section class="py-12 bg-gradient-to-r from-yellow-600 to-yellow-500">
            <div class="max-w-4xl mx-auto px-4 text-center">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Champion</h2>
                <div class="bg-white rounded-xl p-8 shadow-xl inline-block">
                    @if($tournament->champion->logo)
                        <img src="{{ Storage::url($tournament->champion->logo) }}" alt="{{ $tournament->champion->name }}" class="h-24 w-24 object-contain mx-auto mb-4">
                    @endif
                    <h3 class="text-2xl font-bold text-gray-900">{{ $tournament->champion->name }}</h3>
                </div>
                @if($tournament->runnerUp)
                    <p class="mt-6 text-gray-900">
                        Runner-up: <strong>{{ $tournament->runnerUp->name }}</strong>
                    </p>
                @endif
            </div>
        </section>
    @endif

    {{-- Tournament Info Cards --}}
    <section class="py-12 bg-gray-800">
        <div class="max-w-6xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- Dates --}}
                <div class="bg-gray-700 rounded-xl p-6 text-center border border-gray-600">
                    <div class="text-yellow-400 text-3xl mb-3">
                        <i class="far fa-calendar-alt"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Tournament Dates</h3>
                    <p class="text-gray-300">
                        @if($tournament->start_date && $tournament->end_date)
                            {{ $tournament->start_date->format('M d') }} - {{ $tournament->end_date->format('M d, Y') }}
                        @else
                            TBA
                        @endif
                    </p>
                </div>

                {{-- Format --}}
                <div class="bg-gray-700 rounded-xl p-6 text-center border border-gray-600">
                    <div class="text-yellow-400 text-3xl mb-3">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Format</h3>
                    <p class="text-gray-300">
                        {{ ucfirst($settings?->fixture_format ?? 'groups_knockouts') }}
                        @if($settings?->overs_per_match)
                            ({{ $settings->overs_per_match }} Overs)
                        @endif
                    </p>
                </div>

                {{-- Teams --}}
                <div class="bg-gray-700 rounded-xl p-6 text-center border border-gray-600">
                    <div class="text-yellow-400 text-3xl mb-3">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Teams</h3>
                    <p class="text-gray-300">
                        {{ $tournament->actualTeams()->count() }} Teams
                        @if($tournament->groups->count() > 0)
                            in {{ $tournament->groups->count() }} Groups
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- Upcoming Matches --}}
    @if($upcomingMatches->count() > 0)
        <section class="py-12 bg-gray-900">
            <div class="max-w-6xl mx-auto px-4">
                <h2 class="text-2xl font-bold mb-6 text-center">Upcoming Matches</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($upcomingMatches as $match)
                        <a href="{{ route('public.match.show', $match->slug) }}"
                           class="bg-gray-800 rounded-xl p-6 border border-gray-700 hover:border-yellow-500 transition">
                            <div class="text-sm text-gray-400 mb-3">
                                {{ $match->match_date->format('D, M d, Y') }}
                                @if($match->match_time)
                                    at {{ $match->match_time->format('h:i A') }}
                                @endif
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="text-center flex-1">
                                    @if($match->teamA?->logo)
                                        <img src="{{ Storage::url($match->teamA->logo) }}" alt="{{ $match->teamA->name }}" class="h-12 w-12 object-contain mx-auto mb-2">
                                    @else
                                        <div class="h-12 w-12 bg-gray-700 rounded-full mx-auto mb-2 flex items-center justify-center">
                                            <span class="text-lg font-bold">{{ substr($match->teamA?->short_name ?? 'TBA', 0, 2) }}</span>
                                        </div>
                                    @endif
                                    <p class="text-sm font-medium truncate">{{ $match->teamA?->short_name ?? 'TBA' }}</p>
                                </div>
                                <div class="px-4 text-gray-500 font-bold">VS</div>
                                <div class="text-center flex-1">
                                    @if($match->teamB?->logo)
                                        <img src="{{ Storage::url($match->teamB->logo) }}" alt="{{ $match->teamB->name }}" class="h-12 w-12 object-contain mx-auto mb-2">
                                    @else
                                        <div class="h-12 w-12 bg-gray-700 rounded-full mx-auto mb-2 flex items-center justify-center">
                                            <span class="text-lg font-bold">{{ substr($match->teamB?->short_name ?? 'TBA', 0, 2) }}</span>
                                        </div>
                                    @endif
                                    <p class="text-sm font-medium truncate">{{ $match->teamB?->short_name ?? 'TBA' }}</p>
                                </div>
                            </div>
                            @if($match->ground)
                                <div class="text-xs text-gray-500 mt-3 text-center">
                                    <i class="fas fa-map-marker-alt mr-1"></i> {{ $match->ground->name }}
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>
                <div class="text-center mt-6">
                    <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                       class="text-yellow-400 hover:text-yellow-300 font-medium">
                        View All Fixtures <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- Recent Results --}}
    @if($recentResults->count() > 0)
        <section class="py-12 bg-gray-800">
            <div class="max-w-6xl mx-auto px-4">
                <h2 class="text-2xl font-bold mb-6 text-center">Recent Results</h2>
                <div class="space-y-4">
                    @foreach($recentResults as $match)
                        <a href="{{ route('public.match.show', $match->slug) }}"
                           class="block bg-gray-700 rounded-xl p-4 hover:bg-gray-600 transition">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4 flex-1">
                                    <div class="flex items-center space-x-2">
                                        @if($match->teamA?->logo)
                                            <img src="{{ Storage::url($match->teamA->logo) }}" alt="{{ $match->teamA->name }}" class="h-8 w-8 object-contain">
                                        @endif
                                        <span class="font-medium {{ $match->winner_id === $match->team_a_id ? 'text-green-400' : '' }}">
                                            {{ $match->teamA?->short_name ?? 'TBA' }}
                                        </span>
                                    </div>
                                    @if($match->result)
                                        <span class="text-gray-400">
                                            {{ $match->result->team_a_score }}/{{ $match->result->team_a_wickets }}
                                            ({{ $match->result->team_a_overs }})
                                        </span>
                                    @endif
                                </div>
                                <div class="px-4 text-gray-500 text-sm">vs</div>
                                <div class="flex items-center space-x-4 flex-1 justify-end">
                                    @if($match->result)
                                        <span class="text-gray-400">
                                            {{ $match->result->team_b_score }}/{{ $match->result->team_b_wickets }}
                                            ({{ $match->result->team_b_overs }})
                                        </span>
                                    @endif
                                    <div class="flex items-center space-x-2">
                                        <span class="font-medium {{ $match->winner_id === $match->team_b_id ? 'text-green-400' : '' }}">
                                            {{ $match->teamB?->short_name ?? 'TBA' }}
                                        </span>
                                        @if($match->teamB?->logo)
                                            <img src="{{ Storage::url($match->teamB->logo) }}" alt="{{ $match->teamB->name }}" class="h-8 w-8 object-contain">
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if($match->result?->result_summary)
                                <p class="text-sm text-yellow-400 mt-2 text-center">{{ $match->result->result_summary }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Quick Links --}}
    <section class="py-12 bg-gray-900">
        <div class="max-w-4xl mx-auto px-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                   class="bg-gray-800 hover:bg-gray-700 rounded-xl p-6 text-center transition border border-gray-700">
                    <i class="fas fa-calendar-alt text-2xl text-yellow-400 mb-3"></i>
                    <p class="font-medium">Fixtures</p>
                </a>
                <a href="{{ route('public.tournament.point-table', $tournament->slug) }}"
                   class="bg-gray-800 hover:bg-gray-700 rounded-xl p-6 text-center transition border border-gray-700">
                    <i class="fas fa-table text-2xl text-yellow-400 mb-3"></i>
                    <p class="font-medium">Point Table</p>
                </a>
                <a href="{{ route('public.tournament.statistics', $tournament->slug) }}"
                   class="bg-gray-800 hover:bg-gray-700 rounded-xl p-6 text-center transition border border-gray-700">
                    <i class="fas fa-chart-bar text-2xl text-yellow-400 mb-3"></i>
                    <p class="font-medium">Statistics</p>
                </a>
                <a href="{{ route('public.tournament.teams', $tournament->slug) }}"
                   class="bg-gray-800 hover:bg-gray-700 rounded-xl p-6 text-center transition border border-gray-700">
                    <i class="fas fa-users text-2xl text-yellow-400 mb-3"></i>
                    <p class="font-medium">Teams</p>
                </a>
            </div>
        </div>
    </section>
@endsection
