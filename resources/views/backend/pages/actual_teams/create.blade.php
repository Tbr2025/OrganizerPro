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
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team Logo</label>
                        <div class="mt-2">
                            <x-logo-cropper name="team_logo" />
                        </div>
                        @error('team_logo')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Team Name --}}
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team Name
                            <span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required
                            class="form-control mt-1">
                        @error('name')
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

    {{-- This script is needed for the image preview --}}
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
@endsection
