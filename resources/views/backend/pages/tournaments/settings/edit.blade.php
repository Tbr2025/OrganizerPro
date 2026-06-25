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

            @cannot('tournament.settings')
            <div class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m12-6a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-amber-800 dark:text-amber-300">View Only</h3>
                        <p class="text-sm text-amber-700 dark:text-amber-400 mt-0.5">These settings can only be modified by {{ get_setting('app_name') }}. Contact {{ get_setting('app_name') }} to make changes.</p>
                    </div>
                </div>
            </div>
            @endcannot

            <form method="POST" action="{{ route('admin.tournaments.settings.update', $tournament) }}" class="space-y-8" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                {{-- Branding & Design Section --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Branding & Design</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Configure the public tournament page appearance</p>

                    @can('tournament.settings')
                    <div x-data="{
                        primary: '{{ old('primary_color', $settings->primary_color ?? '#1a56db') }}',
                        secondary: '{{ old('secondary_color', $settings->secondary_color ?? '#ffffff') }}',
                        accent: '{{ old('accent_color', $settings->accent_color ?? '#fbbf24') }}',
                        activePreset: null,
                        presets: [
                            { name: 'Classic Gold', icon: 'fas fa-trophy', primary: '#1a1a2e', secondary: '#16213e', accent: '#fbbf24' },
                            { name: 'IPL', icon: 'fas fa-star', primary: '#1b0a3c', secondary: '#2d1b69', accent: '#e23744' },
                            { name: 'T20 World Cup', icon: 'fas fa-globe', primary: '#0c1445', secondary: '#1a237e', accent: '#00bcd4' },
                            { name: 'Big Bash', icon: 'fas fa-fire', primary: '#1a1a2e', secondary: '#2d2d44', accent: '#00e676' },
                            { name: 'PSL', icon: 'fas fa-bolt', primary: '#0d1b2a', secondary: '#1b3a4b', accent: '#4fc3f7' },
                            { name: 'Caribbean Premier', icon: 'fas fa-sun', primary: '#1a0a2e', secondary: '#2e1065', accent: '#ff6f00' },
                            { name: 'The Hundred', icon: 'fas fa-circle-half-stroke', primary: '#121212', secondary: '#1e1e1e', accent: '#e91e63' },
                            { name: 'SA20', icon: 'fas fa-flag', primary: '#003d00', secondary: '#1b5e20', accent: '#fdd835' },
                        ],
                        applyPreset(preset) {
                            this.primary = preset.primary;
                            this.secondary = preset.secondary;
                            this.accent = preset.accent;
                            this.activePreset = preset.name;
                            document.getElementById('primary_color').value = preset.primary;
                            document.getElementById('secondary_color').value = preset.secondary;
                            document.getElementById('accent_color').value = preset.accent;
                        }
                    }">

                    {{-- Template Presets --}}
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Quick Presets</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <template x-for="preset in presets" :key="preset.name">
                                <button type="button"
                                    @click="applyPreset(preset)"
                                    :class="activePreset === preset.name ? 'ring-2 ring-offset-2 ring-indigo-500 dark:ring-offset-gray-900' : ''"
                                    class="relative group flex flex-col items-center gap-1.5 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-indigo-400 dark:hover:border-indigo-500 transition-all cursor-pointer">
                                    {{-- Color preview dots --}}
                                    <div class="flex items-center gap-1">
                                        <span class="w-5 h-5 rounded-full border border-gray-300 dark:border-gray-600 shadow-sm" :style="'background:' + preset.primary"></span>
                                        <span class="w-5 h-5 rounded-full border border-gray-300 dark:border-gray-600 shadow-sm" :style="'background:' + preset.secondary"></span>
                                        <span class="w-5 h-5 rounded-full border border-gray-300 dark:border-gray-600 shadow-sm" :style="'background:' + preset.accent"></span>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300 text-center leading-tight" x-text="preset.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Live Preview --}}
                    <div class="mb-6 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700">
                        <div class="relative h-28" :style="'background: linear-gradient(135deg, ' + primary + ' 0%, ' + secondary + ' 100%);'">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center">
                                    @if($settings->logo || $tournament->logo)
                                        <img src="{{ Storage::url($settings->logo ?? $tournament->logo) }}" alt="Logo" class="h-12 w-12 object-contain rounded-lg mx-auto mb-1 bg-white/20 p-1">
                                    @endif
                                    <p class="text-white font-bold text-sm drop-shadow">{{ $tournament->name }}</p>
                                    <p class="text-xs mt-0.5 font-semibold" :style="'color: ' + accent">Live Preview</p>
                                </div>
                            </div>
                            {{-- Accent bar at bottom --}}
                            <div class="absolute bottom-0 left-0 right-0 h-1" :style="'background: ' + accent"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Logo --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tournament Logo</label>
                            <x-image-dropzone
                                name="logo"
                                :existingImage="$settings->logo ?? $tournament->logo"
                                previewHeight="h-32"
                            />
                            @error('logo') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Background Image --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hero Background Image</label>
                            <x-image-dropzone
                                name="background_image"
                                :existingImage="$settings->background_image"
                                hint="Displayed behind the hero section on the public page"
                                previewHeight="h-32"
                                previewAspect="cover"
                            />
                            @error('background_image') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Flyer Image --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flyer Image</label>
                            <x-image-dropzone
                                name="flyer_image"
                                :existingImage="$settings->flyer_image"
                                hint="Tournament flyer sent with registration confirmations"
                                previewHeight="h-40"
                                previewAspect="cover"
                            />
                            @error('flyer_image') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-4">
                            {{-- Primary Color --}}
                            <div>
                                <label for="primary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Primary Color</label>
                                <div class="flex items-center gap-2 mt-1">
                                    <input type="color" name="primary_color" id="primary_color"
                                        :value="primary"
                                        @input="primary = $event.target.value; activePreset = null"
                                        class="h-10 w-16 rounded border border-gray-300 dark:border-gray-700 cursor-pointer">
                                    <span class="text-xs text-gray-500" x-text="primary"></span>
                                </div>
                            </div>

                            {{-- Secondary Color --}}
                            <div>
                                <label for="secondary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Secondary Color</label>
                                <div class="flex items-center gap-2 mt-1">
                                    <input type="color" name="secondary_color" id="secondary_color"
                                        :value="secondary"
                                        @input="secondary = $event.target.value; activePreset = null"
                                        class="h-10 w-16 rounded border border-gray-300 dark:border-gray-700 cursor-pointer">
                                    <span class="text-xs text-gray-500" x-text="secondary"></span>
                                </div>
                            </div>

                            {{-- Accent Color --}}
                            <div>
                                <label for="accent_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Accent Color</label>
                                <div class="flex items-center gap-2 mt-1">
                                    <input type="color" name="accent_color" id="accent_color"
                                        :value="accent"
                                        @input="accent = $event.target.value; activePreset = null"
                                        class="h-10 w-16 rounded border border-gray-300 dark:border-gray-700 cursor-pointer">
                                    <span class="text-xs text-gray-500" x-text="accent"></span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Used for highlights, badges, and links on the public page</p>
                            </div>
                        </div>
                    </div>
                    </div>
                    @else
                    {{-- Admin: Read-only branding view --}}
                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3 mb-4">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-sm text-amber-700 dark:text-amber-300">Branding is managed by {{ get_setting('app_name') }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Logo Preview --}}
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tournament Logo</p>
                            @if($settings->logo)
                                <img src="{{ Storage::url($settings->logo) }}" alt="Logo" class="w-20 h-20 object-cover rounded-lg">
                            @elseif($tournament->logo)
                                <img src="{{ Storage::url($tournament->logo) }}" alt="Logo" class="w-20 h-20 object-cover rounded-lg">
                            @else
                                <p class="text-sm text-gray-400">No logo set</p>
                            @endif
                        </div>

                        {{-- Color Swatches --}}
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg border border-gray-300 dark:border-gray-600" style="background-color: {{ $settings->primary_color ?? '#1a56db' }};"></div>
                                <div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">Primary</p>
                                    <p class="text-xs text-gray-500">{{ $settings->primary_color ?? '#1a56db' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg border border-gray-300 dark:border-gray-600" style="background-color: {{ $settings->secondary_color ?? '#ffffff' }};"></div>
                                <div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">Secondary</p>
                                    <p class="text-xs text-gray-500">{{ $settings->secondary_color ?? '#ffffff' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg border border-gray-300 dark:border-gray-600" style="background-color: {{ $settings->accent_color ?? '#fbbf24' }};"></div>
                                <div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">Accent</p>
                                    <p class="text-xs text-gray-500">{{ $settings->accent_color ?? '#fbbf24' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endcan
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
                                <input type="text" readonly value="{{ route('public.tournament.registration.player', $tournament->slug) }}"
                                    class="flex-1 text-xs bg-white dark:bg-gray-800 border border-blue-200 dark:border-blue-700 rounded px-2 py-1.5"
                                    id="player-link">
                                <button type="button" onclick="copyLink('player-link')" class="px-2 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded">Copy</button>
                            </div>
                            @endif
                            @if($settings->team_registration_open)
                            <div class="flex items-center gap-2">
                                <input type="text" readonly value="{{ route('public.tournament.registration.team', $tournament->slug) }}"
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
                            <input type="datetime-local" name="registration_deadline" id="registration_deadline"
                                value="{{ old('registration_deadline', $settings->registration_deadline?->format('Y-m-d\TH:i')) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <p class="text-xs text-gray-500 mt-1">Registration auto-closes after this date & time</p>
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

                {{-- Registration Form Fields Configuration --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6" x-data="{
                    fields: @js($fieldConfig),
                    lockedFields: ['name', 'email'],
                    toggleRequired(key) {
                        if (!this.fields[key].visible) {
                            this.fields[key].required = false;
                        }
                    }
                }">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Registration Form Fields</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Configure which fields appear on the player registration form and which are required.</p>

                    @php
                        $groups = \App\Helpers\PlayerFormConfig::fieldGroups();
                        $labels = \App\Helpers\PlayerFormConfig::fieldLabels();
                    @endphp

                    <div class="space-y-4">
                        @foreach($groups as $groupName => $groupFields)
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 bg-gray-50 dark:bg-gray-800 px-3 py-1.5 rounded">{{ $groupName }}</h4>
                            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($groupFields as $fieldKey)
                                <div class="flex items-center justify-between px-3 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $labels[$fieldKey] ?? $fieldKey }}</span>
                                    <div class="flex items-center gap-6">
                                        {{-- Visible toggle --}}
                                        <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                            <span>Visible</span>
                                            <input type="checkbox"
                                                :name="'form_fields[' + '{{ $fieldKey }}' + '][visible]'"
                                                x-model="fields['{{ $fieldKey }}'].visible"
                                                @change="toggleRequired('{{ $fieldKey }}')"
                                                :disabled="lockedFields.includes('{{ $fieldKey }}')"
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                :class="lockedFields.includes('{{ $fieldKey }}') ? 'opacity-50 cursor-not-allowed' : ''">
                                        </label>
                                        {{-- Required toggle --}}
                                        <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                            <span>Required</span>
                                            <input type="checkbox"
                                                :name="'form_fields[' + '{{ $fieldKey }}' + '][required]'"
                                                x-model="fields['{{ $fieldKey }}'].required"
                                                :disabled="!fields['{{ $fieldKey }}'].visible || lockedFields.includes('{{ $fieldKey }}')"
                                                class="h-4 w-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                                                :class="(!fields['{{ $fieldKey }}'].visible || lockedFields.includes('{{ $fieldKey }}')) ? 'opacity-50 cursor-not-allowed' : ''">
                                        </label>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Team Registration Form Fields Configuration --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6" x-data="{
                    teamFields: @js($teamFieldConfig),
                    lockedTeamFields: ['team_name', 'captain_name', 'captain_email'],
                    toggleTeamRequired(key) {
                        if (!this.teamFields[key].visible) {
                            this.teamFields[key].required = false;
                        }
                    }
                }">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Team Registration Form Fields</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Configure which fields appear on the team registration form and which are required.</p>

                    @php
                        $teamGroups = \App\Helpers\TeamFormConfig::fieldGroups();
                        $teamLabels = \App\Helpers\TeamFormConfig::fieldLabels();
                    @endphp

                    <div class="space-y-4">
                        @foreach($teamGroups as $groupName => $groupFields)
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 bg-gray-50 dark:bg-gray-800 px-3 py-1.5 rounded">{{ $groupName }}</h4>
                            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($groupFields as $fieldKey)
                                <div class="flex items-center justify-between px-3 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $teamLabels[$fieldKey] ?? $fieldKey }}</span>
                                    <div class="flex items-center gap-6">
                                        <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                            <span>Visible</span>
                                            <input type="checkbox"
                                                :name="'team_form_fields[' + '{{ $fieldKey }}' + '][visible]'"
                                                x-model="teamFields['{{ $fieldKey }}'].visible"
                                                @change="toggleTeamRequired('{{ $fieldKey }}')"
                                                :disabled="lockedTeamFields.includes('{{ $fieldKey }}')"
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                :class="lockedTeamFields.includes('{{ $fieldKey }}') ? 'opacity-50 cursor-not-allowed' : ''">
                                        </label>
                                        <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                            <span>Required</span>
                                            <input type="checkbox"
                                                :name="'team_form_fields[' + '{{ $fieldKey }}' + '][required]'"
                                                x-model="teamFields['{{ $fieldKey }}'].required"
                                                :disabled="!teamFields['{{ $fieldKey }}'].visible || lockedTeamFields.includes('{{ $fieldKey }}')"
                                                class="h-4 w-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500"
                                                :class="(!teamFields['{{ $fieldKey }}'].visible || lockedTeamFields.includes('{{ $fieldKey }}')) ? 'opacity-50 cursor-not-allowed' : ''">
                                        </label>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Terms & Conditions Content --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Terms & Conditions</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Enter the terms & conditions content. Enable the "Terms & Conditions" field in the Registration Form Fields section above to show it on registration forms.</p>
                    <div>
                        <label for="terms_and_conditions_content" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">T&C Content</label>
                        <textarea name="terms_and_conditions_content" id="terms_and_conditions_content" rows="6"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            placeholder="Enter your tournament terms and conditions here...">{{ old('terms_and_conditions_content', $settings->terms_and_conditions_content) }}</textarea>
                        @error('terms_and_conditions_content') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
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

                {{-- Match Summary Settings --}}
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Match Summary Settings</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="summary_update_mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Summary Update Mode</label>
                            <select name="summary_update_mode" id="summary_update_mode" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="manual" {{ old('summary_update_mode', $settings->summary_update_mode ?? 'manual') == 'manual' ? 'selected' : '' }}>Manual</option>
                                <option value="automatic" {{ old('summary_update_mode', $settings->summary_update_mode ?? 'manual') == 'automatic' ? 'selected' : '' }}>Automatic</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Manual: Update match summary from CricHeroes or enter manually. Automatic: Auto-generate from ball-by-ball data.</p>
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
                @can('tournament.settings')
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
                @endcan
            </form>
        </div>
    </div>

@push('scripts')
@cannot('tournament.settings')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form[action*="settings"]');
        if (!form) return;
        form.querySelectorAll('input, select, textarea').forEach(el => {
            el.disabled = true;
            el.classList.add('opacity-60', 'cursor-not-allowed');
        });
        form.querySelectorAll('button[type="button"]').forEach(el => {
            el.disabled = true;
            el.classList.add('opacity-40', 'pointer-events-none');
        });
    });
</script>
@endcannot
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
