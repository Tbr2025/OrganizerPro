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

                    {{-- Current Status Banner --}}
                    <div class="mb-4 p-3 rounded-lg {{ ($settings->player_registration_open || $settings->team_registration_open) ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' }}">
                        <div class="flex items-center gap-2">
                            @if($settings->player_registration_open || $settings->team_registration_open)
                                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-sm font-medium text-green-700 dark:text-green-300">Registration is OPEN</span>
                            @else
                                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-sm font-medium text-red-700 dark:text-red-300">Registration is CLOSED</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                            Player: {{ $settings->player_registration_open ? 'Open' : 'Closed' }} |
                            Team: {{ $settings->team_registration_open ? 'Open' : 'Closed' }}
                            @if($settings->registration_deadline)
                                | Deadline: {{ $settings->registration_deadline->format('d M Y') }}
                            @endif
                        </p>
                    </div>

                    {{-- Registration Toggles --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="p-4 rounded-lg border {{ old('player_registration_open', $settings->player_registration_open) ? 'border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900/20' : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800' }}">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label for="player_registration_open" class="text-sm font-medium text-gray-900 dark:text-white cursor-pointer">Player Registration</label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Allow individual players to register</p>
                                </div>
                                <input type="checkbox" name="player_registration_open" id="player_registration_open" value="1"
                                    {{ old('player_registration_open', $settings->player_registration_open) ? 'checked' : '' }}
                                    class="h-5 w-5 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            </div>
                        </div>

                        <div class="p-4 rounded-lg border {{ old('team_registration_open', $settings->team_registration_open) ? 'border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900/20' : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800' }}">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label for="team_registration_open" class="text-sm font-medium text-gray-900 dark:text-white cursor-pointer">Team Registration</label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Allow teams to register for tournament</p>
                                </div>
                                <input type="checkbox" name="team_registration_open" id="team_registration_open" value="1"
                                    {{ old('team_registration_open', $settings->team_registration_open) ? 'checked' : '' }}
                                    class="h-5 w-5 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            </div>
                        </div>
                    </div>

                    {{-- Public Registration Links --}}
                    @if($settings->player_registration_open || $settings->team_registration_open)
                    <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                        <h4 class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-2">Public Registration Links</h4>
                        <p class="text-xs text-blue-600 dark:text-blue-400 mb-3">Share these links with players and teams</p>
                        <div class="space-y-2">
                            @if($settings->player_registration_open)
                            <div class="flex items-center gap-2">
                                <input type="text" readonly value="{{ route('public.tournament.register.player', $tournament->slug) }}"
                                    class="flex-1 text-xs bg-white dark:bg-gray-800 border border-blue-200 dark:border-blue-700 rounded px-2 py-1.5"
                                    id="player-link">
                                <button type="button" onclick="copyLink('player-link')" class="px-2 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded">Copy</button>
                            </div>
                            @endif
                            @if($settings->team_registration_open)
                            <div class="flex items-center gap-2">
                                <input type="text" readonly value="{{ route('public.tournament.register.team', $tournament->slug) }}"
                                    class="flex-1 text-xs bg-white dark:bg-gray-800 border border-blue-200 dark:border-blue-700 rounded px-2 py-1.5"
                                    id="team-link">
                                <button type="button" onclick="copyLink('team-link')" class="px-2 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs rounded">Copy</button>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="registration_deadline" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Registration Deadline</label>
                            <input type="date" name="registration_deadline" id="registration_deadline"
                                value="{{ old('registration_deadline', $settings->registration_deadline?->format('Y-m-d')) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <p class="text-xs text-gray-500 mt-1">Registration auto-closes after this date</p>
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

@push('scripts')
<script>
function copyLink(inputId) {
    const input = document.getElementById(inputId);
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        window.dispatchEvent(new CustomEvent('notify', {
            detail: { variant: 'success', title: 'Copied!', message: 'Link copied to clipboard' }
        }));
    });
}
</script>
@endpush
@endsection
