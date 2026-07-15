@extends('backend.layouts.app')

@section('title', 'Register as Player | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />
        <div class="mb-6">
            <a href="{{ route('team-manager.dashboard') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 flex items-center gap-1 mb-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Dashboard
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Register as Player</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Select a tournament to register yourself as a player</p>
        </div>

        @if($tournaments->isEmpty())
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] p-6 text-center">
                <p class="text-gray-500 dark:text-gray-400">No tournaments found for your teams.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($tournaments as $tournament)
                    @php
                        $firstName = explode(' ', $user->name, 2)[0] ?? '';
                        $lastName = implode(' ', array_slice(explode(' ', $user->name), 1)) ?: '';
                        $regUrl = route('public.tournament.registration.player', ['tournament' => $tournament->slug]) . '?' . http_build_query([
                            'prefill_first_name' => $firstName,
                            'prefill_last_name'  => $lastName,
                            'prefill_email'      => $user->email,
                        ]);
                    @endphp
                    <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-white/[0.03] p-5 flex flex-col">
                        <div class="flex items-center gap-3 mb-3">
                            @if($tournament->settings?->logo)
                                <img src="{{ Storage::url($tournament->settings->logo) }}" alt="{{ $tournament->name }}" class="w-10 h-10 rounded-full object-cover">
                            @else
                                <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                            @endif
                            <h3 class="font-semibold text-gray-900 dark:text-white text-sm">{{ $tournament->name }}</h3>
                        </div>
                        <div class="mt-auto pt-3">
                            <a href="{{ $regUrl }}"
                               class="inline-flex items-center justify-center w-full px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Register
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
