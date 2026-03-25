@extends('backend.layouts.app')

@section('title', 'Create Player | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto md:p-6">
        <div class="mb-6">
            <a href="{{ route('team-manager.dashboard') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 flex items-center gap-1 mb-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Dashboard
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create New Player</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Add a new player to {{ $team->name }}</p>
        </div>

        <div class="space-y-6">
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-5 space-y-6 sm:p-6">
                    <form action="{{ route('team-manager.players.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        @if ($errors->any())
                            <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                                <h4 class="text-sm font-medium text-red-800 dark:text-red-300 mb-2">Please fix the following errors:</h4>
                                <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400 space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (session('success'))
                            <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                                <p class="text-sm text-green-800 dark:text-green-300">{{ session('success') }}</p>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">

                            @php
                                $fields = [
                                    'name' => 'Player Name',
                                    'email' => 'Email',
                                    'mobile_number_full' => 'Full Mobile Number',
                                    'cricheroes_number_full' => 'Full Cricheroes Number',
                                    'jersey_name' => 'Jersey Name',
                                    'jersey_number' => 'Jersey Number',
                                ];
                            @endphp

                            {{-- Basic Inputs --}}
                            @foreach ($fields as $field => $label)
                                <div class="space-y-1">
                                    <label for="{{ $field }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $label }}
                                        @if ($field === 'name')
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>
                                    <input type="text" id="{{ $field }}" name="{{ $field }}"
                                        value="{{ old($field) }}"
                                        class="form-control @error($field) border-red-500 @enderror"
                                        @if($field === 'name') required @endif>
                                    @error($field)
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach

                            {{-- Leather Ball Profile Stats --}}
                            <div class="col-span-1 sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
                                @php
                                    $stats = [
                                        'total_matches' => 'Total Matches',
                                        'total_runs' => 'Total Runs',
                                        'total_wickets' => 'Total Wickets',
                                    ];
                                @endphp

                                @foreach ($stats as $field => $label)
                                    <div class="space-y-1">
                                        <label for="{{ $field }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ $label }}
                                        </label>
                                        <input type="number" name="{{ $field }}" id="{{ $field }}"
                                            value="{{ old($field, 0) }}"
                                            class="form-control @error($field) border-red-500 @enderror" min="0">
                                        @error($field)
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>

                            {{-- Player Location --}}
                            @if($locations->count() > 0)
                            <div class="space-y-1">
                                <label for="location_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Player Location
                                </label>
                                <select name="location_id" id="location_id" class="form-control">
                                    <option value="">-- Select Location --</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}"
                                            {{ old('location_id') == $location->id ? 'selected' : '' }}>
                                            {{ $location->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('location_id')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            @endif

                            {{-- Dropdowns --}}
                            @php
                                $dropdowns = [
                                    'kit_size_id' => [
                                        'label' => 'Jersey Size',
                                        'options' => $kitSizes,
                                        'optionField' => 'size',
                                    ],
                                    'batting_profile_id' => [
                                        'label' => 'Batting Profile',
                                        'options' => $battingProfiles,
                                        'optionField' => 'style',
                                    ],
                                    'bowling_profile_id' => [
                                        'label' => 'Bowling Profile',
                                        'options' => $bowlingProfiles,
                                        'optionField' => 'style',
                                    ],
                                    'player_type_id' => [
                                        'label' => 'Player Type',
                                        'options' => $playerTypes,
                                        'optionField' => 'type',
                                    ],
                                ];
                            @endphp

                            @foreach ($dropdowns as $field => $config)
                                @if($config['options']->count() > 0)
                                <div class="space-y-1">
                                    <label for="{{ $field }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $config['label'] }}
                                    </label>
                                    <select name="{{ $field }}" id="{{ $field }}" class="form-control">
                                        <option value="">-- Select {{ $config['label'] }} --</option>
                                        @foreach ($config['options'] as $option)
                                            <option value="{{ $option->id }}"
                                                {{ old($field) == $option->id ? 'selected' : '' }}>
                                                {{ $option->{$config['optionField']} }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error($field)
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                @endif
                            @endforeach

                            {{-- Player Image Upload --}}
                            <div class="sm:col-span-2">
                                <label for="image_path" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Player Image
                                </label>

                                <div x-data="{
                                    previewUrl: '',
                                    handleFileChange(event) {
                                        const file = event.target.files[0];
                                        if (file && file.type.startsWith('image/')) {
                                            this.previewUrl = URL.createObjectURL(file);
                                        } else {
                                            this.previewUrl = '';
                                        }
                                    },
                                    dropHandler(event) {
                                        event.preventDefault();
                                        const file = event.dataTransfer.files[0];
                                        if (file && file.type.startsWith('image/')) {
                                            this.$refs.fileInput.files = event.dataTransfer.files;
                                            this.previewUrl = URL.createObjectURL(file);
                                        }
                                    }
                                }" @drop.prevent="dropHandler($event)" @dragover.prevent
                                    class="border-2 border-dashed border-gray-300 hover:border-blue-500 bg-gray-50 dark:bg-gray-800 p-4 rounded-lg text-center cursor-pointer relative"
                                    @click="$refs.fileInput.click()">
                                    <input type="file" name="image_path" id="image_path" accept="image/png,image/jpeg"
                                        class="absolute w-0 h-0 opacity-0" x-ref="fileInput" @change="handleFileChange">

                                    {{-- Image Preview --}}
                                    <template x-if="previewUrl">
                                        <img :src="previewUrl"
                                            class="mx-auto mb-2 h-48 object-contain rounded border border-gray-300" />
                                    </template>

                                    <p x-show="!previewUrl" class="text-gray-600 dark:text-gray-400 text-sm">
                                        Drag & drop or click to upload image (PNG/JPG, max 6MB)
                                    </p>
                                </div>

                                @error('image_path')
                                    <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Boolean Fields --}}
                            @php
                                $checkboxes = [
                                    'is_wicket_keeper' => 'Is Wicket Keeper?',
                                    'transportation_required' => 'Transportation Required?',
                                    'no_travel_plan' => 'No Travel Plan',
                                ];
                            @endphp

                            @foreach ($checkboxes as $field => $label)
                                <div class="space-y-1">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" name="{{ $field }}" value="1"
                                            {{ old($field) ? 'checked' : '' }} class="mr-2">
                                        {{ $label }}
                                    </label>

                                    @if ($field === 'no_travel_plan')
                                        <div class="mt-4 grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="text-xs text-gray-500">From Date</label>
                                                <input type="date" id="travel_date_from" name="travel_date_from"
                                                    value="{{ old('travel_date_from') }}"
                                                    class="border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded-md shadow-sm w-full"
                                                    placeholder="YYYY-MM-DD">
                                            </div>
                                            <div>
                                                <label class="text-xs text-gray-500">To Date</label>
                                                <input type="date" id="travel_date_to" name="travel_date_to"
                                                    value="{{ old('travel_date_to') }}"
                                                    class="border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded-md shadow-sm w-full"
                                                    placeholder="YYYY-MM-DD">
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                        </div>

                        {{-- Submit Buttons --}}
                        <div class="mt-6 flex justify-end space-x-4">
                            <a href="{{ route('team-manager.dashboard') }}"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Cancel
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Create Player
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
