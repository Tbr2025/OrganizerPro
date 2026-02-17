@extends('backend.layouts.app')

@section('title', 'Registration Details | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-4xl md:p-6">
        <x-breadcrumbs :breadcrumbs="[
            ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
            ['label' => $tournament->name],
            ['label' => 'Registrations', 'url' => route('admin.tournaments.registrations.index', $tournament)],
            ['label' => 'Details']
        ]" />

        <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl overflow-hidden">
            {{-- Header --}}
            <div class="p-6 {{ $registration->type == 'team' ? 'bg-gradient-to-r from-purple-600 to-indigo-700' : 'bg-gradient-to-r from-blue-600 to-cyan-700' }}">
                <div class="flex justify-between items-start">
                    <div class="flex items-center gap-4">
                        @if($registration->type == 'team')
                            @if($registration->team_logo)
                                <img src="{{ Storage::url($registration->team_logo) }}" alt="Team Logo" class="w-16 h-16 rounded-xl object-cover border-2 border-white/30">
                            @else
                                <div class="w-16 h-16 rounded-xl bg-white/20 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            @endif
                            <div>
                                <h2 class="text-2xl font-bold text-white">{{ $registration->team_name }}</h2>
                                @if($registration->team_short_name)
                                    <p class="text-white/80 text-sm">({{ $registration->team_short_name }})</p>
                                @endif
                                <span class="inline-flex items-center px-2 py-0.5 mt-1 rounded text-xs font-medium bg-purple-900/50 text-white">
                                    Team Registration
                                </span>
                            </div>
                        @else
                            @if($registration->player?->image_path)
                                <img src="{{ Storage::url($registration->player->image_path) }}" alt="Player" class="w-16 h-16 rounded-full object-cover border-2 border-white/30">
                            @else
                                <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                            @endif
                            <div>
                                <h2 class="text-2xl font-bold text-white">{{ $registration->player->name ?? 'N/A' }}</h2>
                                <span class="inline-flex items-center px-2 py-0.5 mt-1 rounded text-xs font-medium bg-blue-900/50 text-white">
                                    Player Registration
                                </span>
                            </div>
                        @endif
                    </div>

                    @if($registration->status == 'pending')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-400 text-yellow-900">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Pending
                        </span>
                    @elseif($registration->status == 'approved')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-400 text-green-900">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Approved
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-400 text-red-900">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Rejected
                        </span>
                    @endif
                </div>
            </div>

            {{-- Details --}}
            <div class="p-6">
                @if($registration->type == 'team')
                    {{-- Team Registration Details --}}
                    <div class="space-y-6">
                        {{-- Captain Information --}}
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Captain Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</h4>
                                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $registration->captain_name }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</h4>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->captain_email }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</h4>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->captain_phone }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Vice Captain Information --}}
                        @if($registration->vice_captain_name)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Vice Captain Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</h4>
                                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $registration->vice_captain_name }}</p>
                                </div>
                                @if($registration->vice_captain_phone)
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</h4>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->vice_captain_phone }}</p>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        {{-- Team Description --}}
                        @if($registration->team_description)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Team Description</h3>
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $registration->team_description }}</p>
                            </div>
                        </div>
                        @endif
                    </div>
                @else
                    {{-- Player Registration Details --}}
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Player Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</h4>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->player->email ?? 'N/A' }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phone</h4>
                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->player->mobile_number_full ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        @if($registration->actualTeam)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Assigned Team</h3>
                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $registration->actualTeam->name }}</p>
                            </div>
                        </div>
                        @endif
                    </div>
                @endif

                {{-- Registration Meta --}}
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Registration Date</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->created_at->format('M d, Y') }}</p>
                            <p class="text-xs text-gray-500">{{ $registration->created_at->format('h:i A') }}</p>
                        </div>
                        @if($registration->processed_at)
                        <div>
                            <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Processed Date</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->processed_at->format('M d, Y') }}</p>
                            <p class="text-xs text-gray-500">{{ $registration->processed_at->format('h:i A') }}</p>
                        </div>
                        @endif
                        @if($registration->processedBy)
                        <div>
                            <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Processed By</h4>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $registration->processedBy->name }}</p>
                        </div>
                        @endif
                    </div>

                    @if($registration->remarks)
                    <div class="mt-4">
                        <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Remarks</h4>
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $registration->remarks }}</p>
                    </div>
                    @endif
                </div>

                {{-- Actions --}}
                @if($registration->status == 'pending')
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <a href="{{ route('admin.tournaments.registrations.index', $tournament) }}"
                           class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back to List
                        </a>
                        <div class="flex gap-3">
                            <form action="{{ route('admin.tournaments.registrations.reject', [$tournament, $registration]) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Reject Registration
                                </button>
                            </form>
                            <form action="{{ route('admin.tournaments.registrations.approve', [$tournament, $registration]) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Approve Registration
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('admin.tournaments.registrations.index', $tournament) }}"
                           class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back to List
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
