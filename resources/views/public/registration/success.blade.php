@extends('public.tournament.layouts.app')

@section('title', 'Registration Successful - ' . $tournament->name)

@section('content')
    <div class="max-w-xl mx-auto px-4 py-16">
        <div class="bg-gray-800 rounded-xl p-8 text-center border border-gray-700">
            {{-- Success Icon --}}
            <div class="mb-6">
                <div class="w-20 h-20 bg-green-600 rounded-full mx-auto flex items-center justify-center">
                    <i class="fas fa-check text-4xl text-white"></i>
                </div>
            </div>

            {{-- Message --}}
            <h1 class="text-2xl font-bold mb-4 text-green-400">Registration Successful!</h1>
            <p class="text-gray-400 mb-6">
                @if($type === 'player')
                    Thank you for registering as a player for <strong class="text-white">{{ $tournament->name }}</strong>.
                    Your registration is pending approval.
                @else
                    Thank you for registering your team for <strong class="text-white">{{ $tournament->name }}</strong>.
                    Your registration is pending approval.
                @endif
            </p>

            {{-- What's Next --}}
            <div class="bg-gray-700 rounded-lg p-4 mb-6 text-left">
                <h2 class="font-semibold mb-3 text-yellow-400">What happens next?</h2>
                <ul class="text-sm text-gray-300 space-y-2">
                    <li class="flex items-start gap-2">
                        <i class="fas fa-envelope text-gray-500 mt-1"></i>
                        <span>You will receive a confirmation email shortly.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-clock text-gray-500 mt-1"></i>
                        <span>The organizers will review your registration.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-bell text-gray-500 mt-1"></i>
                        <span>You will be notified once your registration is approved.</span>
                    </li>
                </ul>
            </div>

            {{-- Share Tournament --}}
            <div class="bg-gray-700 rounded-lg p-4 mb-6">
                <h2 class="font-semibold mb-3 text-yellow-400">Spread the word!</h2>
                <p class="text-sm text-gray-300 mb-4">Invite your friends to register for this tournament.</p>
                @php
                    $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                    $registrationUrl = $type === 'player'
                        ? route('public.tournament.register.player', $tournament->slug)
                        : route('public.tournament.register.team', $tournament->slug);
                    $shareMessage = $whatsappService->getRegistrationShareMessage($tournament, $type);
                @endphp
                <x-share-buttons
                    :url="$registrationUrl"
                    :title="$tournament->name . ' - Registration Open'"
                    :description="'Register for ' . $tournament->name"
                    :whatsappMessage="$shareMessage"
                    variant="compact"
                    :showLabel="false"
                    class="justify-center"
                />
            </div>

            {{-- Actions --}}
            <div class="space-y-3">
                <a href="{{ route('public.tournament.show', $tournament->slug) }}"
                   class="block w-full bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-bold py-3 px-6 rounded-lg transition">
                    Back to Tournament
                </a>
            </div>
        </div>
    </div>
@endsection
