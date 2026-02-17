@extends('backend.layouts.app')

@section('title', 'Tournament Settings | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto max-w-4xl md:p-6">
        <x-breadcrumbs :breadcrumbs="[
            ['label' => 'Tournaments', 'url' => route('admin.tournaments.index')],
            ['label' => $tournament->name],
            ['label' => 'Settings']
        ]" />

        <div class="mt-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-md rounded-xl p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">{{ $tournament->name }} - Settings</h2>

            <form method="POST" action="{{ route('admin.tournaments.settings.update', $tournament) }}" class="space-y-8" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                {{-- Branding Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Branding</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Logo --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tournament Logo</label>
                            @if($settings->logo)
                                <img src="{{ Storage::url($settings->logo) }}" alt="Logo" class="w-20 h-20 object-cover rounded-lg mb-2">
                            @endif
                            <input type="file" name="logo" accept="image/*"
                                class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900 dark:file:text-indigo-300">
                            @error('logo') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Background Image --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Background Image</label>
                            @if($settings->background_image)
                                <img src="{{ Storage::url($settings->background_image) }}" alt="Background" class="w-32 h-20 object-cover rounded-lg mb-2">
                            @endif
                            <input type="file" name="background_image" accept="image/*"
                                class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900 dark:file:text-indigo-300">
                            @error('background_image') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Primary Color --}}
                        <div>
                            <label for="primary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Primary Color</label>
                            <input type="color" name="primary_color" id="primary_color"
                                value="{{ old('primary_color', $settings->primary_color ?? '#4F46E5') }}"
                                class="mt-1 h-10 w-full rounded border border-gray-300 dark:border-gray-700">
                        </div>

                        {{-- Secondary Color --}}
                        <div>
                            <label for="secondary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Secondary Color</label>
                            <input type="color" name="secondary_color" id="secondary_color"
                                value="{{ old('secondary_color', $settings->secondary_color ?? '#10B981') }}"
                                class="mt-1 h-10 w-full rounded border border-gray-300 dark:border-gray-700">
                        </div>
                    </div>
                </div>

                {{-- Registration Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Registration Settings</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex items-center">
                            <input type="checkbox" name="player_registration_open" id="player_registration_open" value="1"
                                {{ old('player_registration_open', $settings->player_registration_open) ? 'checked' : '' }}
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                            <label for="player_registration_open" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Player Registration Open</label>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="team_registration_open" id="team_registration_open" value="1"
                                {{ old('team_registration_open', $settings->team_registration_open) ? 'checked' : '' }}
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                            <label for="team_registration_open" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Team Registration Open</label>
                        </div>

                        <div>
                            <label for="registration_deadline" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Registration Deadline</label>
                            <input type="date" name="registration_deadline" id="registration_deadline"
                                value="{{ old('registration_deadline', $settings->registration_deadline?->format('Y-m-d')) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>

                        <div>
                            <label for="max_players_per_team" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Max Players Per Team</label>
                            <input type="number" name="max_players_per_team" id="max_players_per_team" min="1" max="50"
                                value="{{ old('max_players_per_team', $settings->max_players_per_team ?? 15) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>

                        <div>
                            <label for="min_players_per_team" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Min Players Per Team</label>
                            <input type="number" name="min_players_per_team" id="min_players_per_team" min="1" max="50"
                                value="{{ old('min_players_per_team', $settings->min_players_per_team ?? 11) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                    </div>
                </div>

                {{-- Match Settings --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Match Settings</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="format" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tournament Format</label>
                            <select name="format" id="format" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="group_knockout" {{ old('format', $settings->format) == 'group_knockout' ? 'selected' : '' }}>Group + Knockout</option>
                                <option value="league" {{ old('format', $settings->format) == 'league' ? 'selected' : '' }}>League</option>
                                <option value="knockout" {{ old('format', $settings->format) == 'knockout' ? 'selected' : '' }}>Knockout</option>
                            </select>
                        </div>

                        <div>
                            <label for="overs_per_match" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Overs Per Match</label>
                            <input type="number" name="overs_per_match" id="overs_per_match" min="1" max="50"
                                value="{{ old('overs_per_match', $settings->overs_per_match ?? 20) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>

                        <div>
                            <label for="number_of_groups" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Number of Groups</label>
                            <input type="number" name="number_of_groups" id="number_of_groups" min="1" max="16"
                                value="{{ old('number_of_groups', $settings->number_of_groups ?? 2) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-6">
                        <div class="flex items-center">
                            <input type="checkbox" name="has_quarter_finals" id="has_quarter_finals" value="1"
                                {{ old('has_quarter_finals', $settings->has_quarter_finals) ? 'checked' : '' }}
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                            <label for="has_quarter_finals" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Quarter Finals</label>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="has_semi_finals" id="has_semi_finals" value="1"
                                {{ old('has_semi_finals', $settings->has_semi_finals) ? 'checked' : '' }}
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                            <label for="has_semi_finals" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Semi Finals</label>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="has_third_place" id="has_third_place" value="1"
                                {{ old('has_third_place', $settings->has_third_place) ? 'checked' : '' }}
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                            <label for="has_third_place" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Third Place Match</label>
                        </div>
                    </div>
                </div>

                {{-- Points System --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Points System</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div>
                            <label for="points_per_win" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Win</label>
                            <input type="number" name="points_per_win" id="points_per_win" min="0" max="10"
                                value="{{ old('points_per_win', $settings->points_per_win ?? 2) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>

                        <div>
                            <label for="points_per_tie" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tie</label>
                            <input type="number" name="points_per_tie" id="points_per_tie" min="0" max="10"
                                value="{{ old('points_per_tie', $settings->points_per_tie ?? 1) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>

                        <div>
                            <label for="points_per_no_result" class="block text-sm font-medium text-gray-700 dark:text-gray-300">No Result</label>
                            <input type="number" name="points_per_no_result" id="points_per_no_result" min="0" max="10"
                                value="{{ old('points_per_no_result', $settings->points_per_no_result ?? 1) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>

                        <div>
                            <label for="points_per_loss" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Loss</label>
                            <input type="number" name="points_per_loss" id="points_per_loss" min="0" max="10"
                                value="{{ old('points_per_loss', $settings->points_per_loss ?? 0) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                    </div>
                </div>

                {{-- Contact Information --}}
                <div class="pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Contact Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="contact_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Email</label>
                            <input type="email" name="contact_email" id="contact_email"
                                value="{{ old('contact_email', $settings->contact_email) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>

                        <div>
                            <label for="contact_phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Phone</label>
                            <input type="text" name="contact_phone" id="contact_phone"
                                value="{{ old('contact_phone', $settings->contact_phone) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>

                        <div class="md:col-span-2">
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <textarea name="description" id="description" rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">{{ old('description', $settings->description) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('admin.tournaments.index') }}"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-white border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cancel
                    </a>

                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
