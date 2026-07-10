@extends('public.tournament.layouts.app')

@section('title', 'Registration Closed - ' . $tournament->name)

@section('content')
    @php
        $status = $tournamentStatus ?? 'closed';
        // Check both registration-level and tournament-level status maps
        $statusConfig = \App\Models\TournamentSetting::REGISTRATION_STATUSES[$status]
            ?? \App\Models\TournamentSetting::STATUSES[$status]
            ?? \App\Models\TournamentSetting::REGISTRATION_STATUSES['closed'];
        $iconMap = [
            'paused' => 'fa-pause-circle',
            'coming_soon' => 'fa-clock',
            'pending' => 'fa-clock',
            'draft' => 'fa-pencil-alt',
            'closed' => 'fa-lock',
            'completed' => 'fa-trophy',
        ];
        $icon = $iconMap[$status] ?? 'fa-lock';
        $colorMap = [
            'yellow' => ['bg' => 'bg-yellow-700', 'text' => 'text-yellow-500', 'border' => 'border-yellow-700'],
            'blue' => ['bg' => 'bg-blue-700', 'text' => 'text-blue-500', 'border' => 'border-blue-700'],
            'gray' => ['bg' => 'bg-gray-700', 'text' => 'text-gray-500', 'border' => 'border-gray-700'],
            'red' => ['bg' => 'bg-red-700', 'text' => 'text-red-500', 'border' => 'border-red-700'],
            'green' => ['bg' => 'bg-green-700', 'text' => 'text-green-500', 'border' => 'border-green-700'],
        ];
        $colors = $colorMap[$statusConfig['color']] ?? $colorMap['red'];
        $typeLabel = ($type ?? 'player') === 'team' ? 'Team' : 'Player';
    @endphp

    <div class="max-w-xl mx-auto px-4 py-16">
        <div class="bg-gray-800 rounded-xl p-8 text-center border {{ $colors['border'] }}">
            {{-- Icon --}}
            <div class="mb-6">
                <div class="w-20 h-20 {{ $colors['bg'] }} rounded-full mx-auto flex items-center justify-center">
                    <i class="fas {{ $icon }} text-4xl {{ $colors['text'] }}"></i>
                </div>
            </div>

            {{-- Message --}}
            <h1 class="text-2xl font-bold mb-4">{{ $typeLabel }} Registration — {{ $statusConfig['label'] }}</h1>
            <p class="text-gray-400 mb-6">
                {{ $typeLabel }} {{ lcfirst($statusConfig['message']) }} for <strong class="text-white">{{ $tournament->name }}</strong>.
            </p>

            {{-- Deadline info --}}
            @if($tournament->settings?->registration_deadline && $tournament->settings->registration_deadline->isPast())
                <p class="text-sm text-gray-500 mb-6">
                    Registration ended on {{ $tournament->settings->registration_deadline->format('F d, Y') }}
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
