@extends('backend.layouts.app')

@section('title', 'Tournament Settings | ' . $tournament->name)

@section('admin-content')
<div class="p-4 mx-auto max-w-4xl md:p-6 lg:p-8">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Tournament Settings</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tournament->name }}</p>
        </div>
        <a href="{{ route('public.tournament.show', $tournament->slug) }}" target="_blank"
           class="btn btn-secondary inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
            View Public Page
        </a>
    </div>

    <form action="{{ route('admin.tournaments.settings.update', $tournament) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- Branding Section --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 mb-6">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Branding</h2>
            </div>
            <div class="p-6 space-y-6">
                {{-- Logo --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tournament Logo</label>
                    <input type="file" name="logo" accept="image/*" class="form-control">
                    @if($settings?->logo)
                        <div class="mt-2">
                            <img src="{{ Storage::url($settings->logo) }}" alt="Logo" class="h-16 w-16 object-contain rounded">
                        </div>
                    @endif
                    @error('logo')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Background Image --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Background Image</label>
                    <input type="file" name="background_image" accept="image/*" class="form-control">
                    @if($settings?->background_image)
                        <div class="mt-2">
                            <img src="{{ Storage::url($settings->background_image) }}" alt="Background" class="h-24 w-auto object-cover rounded">
                        </div>
                    @endif
                    @error('background_image')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Colors --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Primary Color</label>
                        <input type="color" name="primary_color" value="{{ $settings?->primary_color ?? '#1f2937' }}" class="form-control h-10">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Secondary Color</label>
                        <input type="color" name="secondary_color" value="{{ $settings?->secondary_color ?? '#374151' }}" class="form-control h-10">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Accent Color</label>
                        <input type="color" name="accent_color" value="{{ $settings?->accent_color ?? '#fbbf24' }}" class="form-control h-10">
                    </div>
                </div>
            </div>
        </div>

        {{-- Registration Section --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 mb-6">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Registration Settings</h2>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Player Registration --}}
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-700 dark:text-gray-300">Player Registration</p>
                            <p class="text-sm text-gray-500">Allow players to register for this tournament</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="player_registration_enabled" value="1" class="sr-only peer"
                                   {{ $settings?->player_registration_enabled ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    {{-- Team Registration --}}
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-700 dark:text-gray-300">Team Registration</p>
                            <p class="text-sm text-gray-500">Allow teams to register for this tournament</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="team_registration_enabled" value="1" class="sr-only peer"
                                   {{ $settings?->team_registration_enabled ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>

                {{-- Registration Dates --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Player Registration End Date</label>
                        <input type="date" name="player_registration_end_date"
                               value="{{ $settings?->player_registration_end_date?->format('Y-m-d') }}"
                               class="form-control">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Team Registration End Date</label>
                        <input type="date" name="team_registration_end_date"
                               value="{{ $settings?->team_registration_end_date?->format('Y-m-d') }}"
                               class="form-control">
                    </div>
                </div>
            </div>
        </div>

        {{-- Fixture Settings --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 mb-6">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Fixture Settings</h2>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fixture Format</label>
                        <select name="fixture_format" class="form-control">
                            <option value="groups_knockouts" {{ $settings?->fixture_format === 'groups_knockouts' ? 'selected' : '' }}>Groups + Knockouts</option>
                            <option value="league" {{ $settings?->fixture_format === 'league' ? 'selected' : '' }}>League (Round Robin)</option>
                            <option value="knockouts_only" {{ $settings?->fixture_format === 'knockouts_only' ? 'selected' : '' }}>Knockouts Only</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Number of Groups</label>
                        <input type="number" name="number_of_groups" value="{{ $settings?->number_of_groups ?? 2 }}" min="1" max="10" class="form-control">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Overs Per Match</label>
                        <input type="number" name="overs_per_match" value="{{ $settings?->overs_per_match ?? 20 }}" min="1" class="form-control">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Matches Per Week</label>
                        <input type="number" name="matches_per_week" value="{{ $settings?->matches_per_week ?? 5 }}" min="1" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        {{-- Points System --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 mb-6">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Points System</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Points for Win</label>
                        <input type="number" name="points_for_win" value="{{ $settings?->points_for_win ?? 2 }}" min="0" class="form-control">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Points for Tie</label>
                        <input type="number" name="points_for_tie" value="{{ $settings?->points_for_tie ?? 1 }}" min="0" class="form-control">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Points for Loss</label>
                        <input type="number" name="points_for_loss" value="{{ $settings?->points_for_loss ?? 0 }}" min="0" class="form-control">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Points for No Result</label>
                        <input type="number" name="points_for_no_result" value="{{ $settings?->points_for_no_result ?? 1 }}" min="0" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        {{-- Notification Settings --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 mb-6">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Notification Settings</h2>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div>
                        <p class="font-medium text-gray-700 dark:text-gray-300">Match Posters</p>
                        <p class="text-sm text-gray-500">Auto-send match posters before scheduled matches</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="match_poster_enabled" value="1" class="sr-only peer"
                               {{ $settings?->match_poster_enabled ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Days Before Match to Send Poster</label>
                    <input type="number" name="poster_days_before_match" value="{{ $settings?->poster_days_before_match ?? 2 }}" min="1" max="7" class="form-control w-32">
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex justify-end gap-4">
            <a href="{{ route('admin.tournaments.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>
@endsection
