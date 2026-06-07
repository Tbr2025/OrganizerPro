@extends('backend.layouts.app')

@section('title', 'Create New Team | ' . config('app.name'))

@section('admin-content')
    <x-breadcrumbs :breadcrumbs="[
        ['name' => 'Dashboard', 'route' => route('admin.dashboard')],
        ['name' => 'Teams'],
        ['name' => 'Create']
    ]" />

    <div class="p-4 mx-auto max-w-4xl md:p-6 lg:p-8">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Create a New Team</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Fill in the details below to register a new team.</p>
            </div>
        </div>

        {{-- Form Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md border border-gray-200 dark:border-gray-700">
            <form action="{{ route('admin.actual-teams.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="p-6 space-y-6">
                    {{-- Team Logo Upload with Cropper --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Team Logo</label>
                        <x-logo-cropper name="team_logo" />
                        @error('team_logo')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Team Name & Short Name --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team Name
                                <span class="text-red-500">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required
                                class="form-control mt-1">
                            @error('name')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="short_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Short Name</label>
                            <input type="text" id="short_name" name="short_name" value="{{ old('short_name') }}"
                                class="form-control mt-1" placeholder="e.g., MCC">
                            @error('short_name')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Location --}}
                    <div>
                        <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Location / District</label>
                        <input type="text" id="location" name="location" value="{{ old('location') }}"
                            class="form-control mt-1" placeholder="e.g., Ernakulam">
                        <p class="text-xs text-gray-500 mt-1">Displayed on match posters</p>
                        @error('location')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Team Colors --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="primary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Primary Color</label>
                            <div class="flex items-center gap-2 mt-1">
                                <input type="color" id="primary_color" name="primary_color"
                                    value="{{ old('primary_color', '#00BCD4') }}"
                                    class="w-12 h-10 rounded cursor-pointer border-0"
                                    oninput="document.getElementById('primary_color_text_c').value = this.value">
                                <input type="text" id="primary_color_text_c"
                                    value="{{ old('primary_color', '#00BCD4') }}"
                                    class="form-control flex-1" readonly>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Used for poster background accent</p>
                        </div>
                        <div>
                            <label for="secondary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Secondary Color</label>
                            <div class="flex items-center gap-2 mt-1">
                                <input type="color" id="secondary_color" name="secondary_color"
                                    value="{{ old('secondary_color', '#ffffff') }}"
                                    class="w-12 h-10 rounded cursor-pointer border-0"
                                    oninput="document.getElementById('secondary_color_text_c').value = this.value">
                                <input type="text" id="secondary_color_text_c"
                                    value="{{ old('secondary_color', '#ffffff') }}"
                                    class="form-control flex-1" readonly>
                            </div>
                        </div>
                    </div>

                    {{-- Sponsor Logo Upload --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Team Sponsor Logo</label>
                        <x-image-dropzone
                            name="sponsor_logo"
                            hint="Displayed on match posters below team name"
                            previewHeight="h-32"
                        />
                        @error('sponsor_logo')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Captain Image Upload --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Captain / Featured Player Image</label>
                        <x-player-image-upload
                            name="captain_image"
                            mode="captain"
                        />
                        @error('captain_image')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Organization --}}
                    <div>
                        <label for="organization_id"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">Organization <span
                                class="text-red-500">*</span></label>
                        <select id="organization_id" name="organization_id" required class="form-control mt-1">
                            <option value="">Select Organization</option>
                            @foreach ($organizations as $org)
                                <option value="{{ $org->id }}"
                                    {{ old('organization_id') == $org->id ? 'selected' : '' }}>{{ $org->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('organization_id')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Team Scope Toggle --}}
                    <div x-data="{ teamScope: '{{ old('team_scope', 'tournament') }}' }">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Team Scope <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-4">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="radio" name="team_scope" value="tournament" x-model="teamScope"
                                    class="form-radio text-blue-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Open Tournament</span>
                            </label>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="radio" name="team_scope" value="global" x-model="teamScope"
                                    class="form-radio text-purple-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Global</span>
                            </label>
                        </div>

                        {{-- Global info banner --}}
                        <div x-show="teamScope === 'global'" x-cloak
                            class="mt-3 p-3 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-purple-600 dark:text-purple-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm text-purple-700 dark:text-purple-300">This team will be available in <strong>all tournaments</strong> within the selected organization.</p>
                            </div>
                        </div>

                        {{-- Tournaments (only for Open Tournament) --}}
                        <div x-show="teamScope === 'tournament'" x-cloak class="mt-4">
                            <x-inputs.combobox
                                name="tournament_ids[]"
                                label="Tournaments"
                                placeholder="Select Tournaments"
                                :options="$tournaments->map(fn($t) => ['value' => (string) $t->id, 'label' => $t->name])->toArray()"
                                :selected="old('tournament_ids', [])"
                                :multiple="true"
                                :searchable="true"
                                :required="false"
                            />
                            @error('tournament_ids')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            @error('tournament_ids.*')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Form Footer with Buttons --}}
                {{-- Form Footer with Buttons --}}
                <div
                    class="p-6 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
                    <a href="{{ route('admin.actual-teams.index') }}" class="btn btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Create Team
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection
