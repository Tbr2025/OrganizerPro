@extends('backend.layouts.app')

@section('title', 'Create New Team | ' . config('app.name'))

@section('admin-content')
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
                    {{-- Team Logo Upload --}}
                    <div x-data="{
                        previewUrl: '',
                        handleFileChange(event) {
                            const file = event.target.files[0];
                            if (file && file.type.startsWith('image/')) {
                                this.previewUrl = URL.createObjectURL(file);
                            } else {
                                this.previewUrl = '';
                            }
                        }
                    }">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Team Logo</label>
                        <div class="mt-2 flex items-center gap-4">
                            {{-- Image Preview --}}
                            <span class="inline-block h-20 w-20 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-700">
                                <template x-if="!previewUrl">
                                    <svg class="h-full w-full text-gray-300 dark:text-gray-500" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M24 20.993V24H0v-2.997A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                </template>
                                <template x-if="previewUrl">
                                    <img :src="previewUrl" alt="Team Logo Preview" class="h-full w-full object-cover">
                                </template>
                            </span>
                            {{-- Upload Button --}}
                            <label for="team_logo"
                                class="cursor-pointer bg-white dark:bg-gray-700 py-2 px-3 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm leading-4 font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <span>Upload Logo</span>
                                <input id="team_logo" name="team_logo" type="file" class="sr-only"
                                    @change="handleFileChange">
                            </label>
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

                        {{-- Tournament --}}
                        <div>
                            <label for="tournament_id"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tournament <span
                                    class="text-red-500">*</span></label>
                            <select id="tournament_id" name="tournament_id" required class="form-control mt-1">
                                <option value="">Select Tournament</option>
                                @foreach ($tournaments as $t)
                                    <option value="{{ $t->id }}"
                                        {{ old('tournament_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                @endforeach
                            </select>
                            @error('tournament_id')
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
