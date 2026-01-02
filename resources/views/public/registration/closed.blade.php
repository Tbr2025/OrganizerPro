@extends('public.tournament.layouts.app')

@section('title', 'Registration Closed - ' . $tournament->name)

@section('content')
    <div class="max-w-xl mx-auto px-4 py-16">
        <div class="bg-gray-800 rounded-xl p-8 text-center border border-gray-700">
            {{-- Icon --}}
            <div class="mb-6">
                <div class="w-20 h-20 bg-gray-700 rounded-full mx-auto flex items-center justify-center">
                    <i class="fas fa-lock text-4xl text-gray-500"></i>
                </div>
            </div>

            {{-- Message --}}
            <h1 class="text-2xl font-bold mb-4">Registration Closed</h1>
            <p class="text-gray-400 mb-6">
                @if($type === 'player')
                    Player registration for <strong class="text-white">{{ $tournament->name }}</strong> is currently closed.
                @else
                    Team registration for <strong class="text-white">{{ $tournament->name }}</strong> is currently closed.
                @endif
            </p>

            {{-- Tournament Status Info --}}
            @if($tournament->settings?->player_registration_end_date && $tournament->settings->player_registration_end_date->isPast())
                <p class="text-sm text-gray-500 mb-6">
                    Registration ended on {{ $tournament->settings->player_registration_end_date->format('F d, Y') }}
                </p>
            @endif

            {{-- Actions --}}
            <div class="space-y-3">
                <a href="{{ route('public.tournament.show', $tournament->slug) }}"
                   class="block w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-3 px-6 rounded-lg transition">
                    View Tournament Details
                </a>
                <a href="{{ route('public.tournament.fixtures', $tournament->slug) }}"
                   class="block w-full bg-gray-700 hover:bg-gray-600 text-white font-medium py-3 px-6 rounded-lg transition">
                    View Fixtures
                </a>
            </div>
        </div>
    </div>
@endsection
