@extends('public.tournament.layouts.app')

@section('title', 'Registration Successful - ' . $tournament->name)

@push('styles')
<style>
    .reg-section { padding: 1.75rem; border-radius: 1rem; margin-bottom: 1.25rem; }
    .reg-banner { position: relative; overflow: hidden; border-radius: 1rem; margin-bottom: 1.25rem; padding: 2.5rem 1.5rem; text-align: center; }
    .reg-banner h1 { color:#fff; text-shadow: 0 2px 12px rgba(0,0,0,0.35); }
</style>
@endpush

@include('public.registration.partials.registration-theme')

@section('content')
    @php
        $registrationUrl = $type === 'team'
            ? route('public.tournament.registration.team', $tournament->slug)
            : route('public.tournament.registration.player', $tournament->slug);
    @endphp
    <div class="max-w-xl mx-auto px-4 py-12">

        {{-- Themed banner --}}
        <div class="reg-banner reveal">
            <div class="mx-auto mb-4 flex items-center justify-center" style="width:80px;height:80px;border-radius:9999px;background:rgba(255,255,255,0.2);">
                <i class="fas fa-check text-3xl" style="color:#fff;"></i>
            </div>
            <h1 class="text-3xl font-bold">Thank You!</h1>
            <p class="text-white/90 mt-2">Your {{ $type === 'team' ? 'team registration' : 'application' }} has been submitted.</p>
        </div>

        <div class="reg-section glass reveal text-center">
            <p class="text-gray-200 mb-4">
                @if($type === 'player')
                    Thanks for registering for <strong class="text-white">{{ $tournament->name }}</strong>.
                @else
                    Thanks for registering your team for <strong class="text-white">{{ $tournament->name }}</strong>.
                @endif
            </p>

            <div class="rounded-lg p-4 mb-6 text-left" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);">
                <ul class="text-sm text-gray-300 space-y-3">
                    <li class="flex items-start gap-3">
                        <i class="fas fa-envelope text-accent mt-0.5"></i>
                        <span>We've <strong class="text-white">emailed you a confirmation</strong> — check your inbox (and spam).</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-hourglass-half text-accent mt-0.5"></i>
                        <span>Your application is <strong class="text-white">under review</strong> by the organizers.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-bell text-accent mt-0.5"></i>
                        <span>You'll be notified by email once it's approved.</span>
                    </li>
                </ul>
            </div>

            {{-- Share --}}
            @php
                $whatsappService = app(\App\Services\Share\WhatsAppShareService::class);
                $shareMessage = $whatsappService->getRegistrationShareMessage($tournament, $type);
            @endphp
            <div class="rounded-lg p-4 mb-6" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);">
                <p class="text-sm text-gray-300 mb-3"><i class="fas fa-bullhorn text-accent mr-1"></i> Invite your friends to register</p>
                <x-share-buttons :url="$registrationUrl" :title="$tournament->name . ' - Registration Open'"
                    :description="'Register for ' . $tournament->name" :whatsappMessage="$shareMessage"
                    variant="compact" :showLabel="false" class="justify-center" />
            </div>

            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('public.tournament.show', $tournament->slug) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white"
                   style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);">
                    <i class="fas fa-arrow-left"></i> Back to Tournament
                </a>
                @if($type === 'player')
                    <a href="{{ $registrationUrl }}"
                       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold"
                       style="background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:var(--primary);">
                        <i class="fas fa-user-plus"></i> Register Another
                    </a>
                @endif
            </div>
        </div>
    </div>
@endsection
