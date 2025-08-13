@extends('backend.layouts.app')

@section('title')
    Edit Player | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto  md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        <div class="space-y-6">
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-5 space-y-6 sm:p-6">
                    <form method="POST" action="{{ route('profileplayers.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">

                            @php
                                $fields = [
                                    'name' => 'Player Name',
                                    'email' => 'Email',
                                    'mobile_number_full' => 'Full Mobile Number (With Country Code without +)',
                                    'cricheroes_number_full' => 'Full Cricheroes Number ( With Country Code without +)',
                                    'jersey_name' => 'Jersey Name',
                                ];
                            @endphp

                            {{-- Basic Inputs --}}
                            @foreach ($fields as $field => $label)
                                <div class="space-y-1">
                                    <label for="{{ $field }}"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $label }} @if (str_contains($field, 'name'))
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>
                                    <div class="flex items-center space-x-2">
                                        {{-- MODIFICATION: Added disabled attribute if field is verified --}}
                                        <input type="text" id="{{ $field }}" name="{{ $field }}"
                                            value="{{ old($field, $player->$field) }}"
                                            class="form-control @error($field) border-red-500 @enderror"
                                            {{ $verifiedFields[$field] ? 'disabled' : '' }}>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="verified_{{ $field }}" value="1"
                                                class="sr-only peer"
                                                {{ old('verified_' . $field, $player['verified_' . $field] ?? false) ? 'checked' : '' }}>

                                            <span class="ml-3 text-sm font-bold flex items-center gap-1">
                                                <span
                                                    class="{{ $verifiedFields[$field] ? 'text-green-500' : 'text-red-500' }}">
                                                    {{ $verifiedFields[$field] ? '✔' : '✖' }}
                                                </span>
                                                <span class="text-gray-800 dark:text-gray-300">
                                                    {{ $verifiedFields[$field] ? 'Verified' : 'Not Verified' }}
                                                </span>
                                            </span>



                                        </label>
                                    </div>
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
                                        <label for="{{ $field }}"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ $label }}
                                        </label>
                                        <input type="number" name="{{ $field }}" id="{{ $field }}"
                                            value="{{ old($field, $player->$field) }}"
                                            class="form-control @error($field) border-red-500 @enderror" min="0">
                                        @error($field)
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                            <div class="space-y-1">
                                <label for="location_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Player Location <span class="text-red-500">*</span>
                                </label>
                                <select name="location_id" id="location_id" class="form-control">
                                    <option value="">-- Select Location --</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}"
                                            {{ old('location_id', $player->location_id) == $location->id ? 'selected' : '' }}>
                                            {{ $location->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('location_id')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Dropdowns --}}
                            @php
                                $dropdowns = [
                                    'team_id' => ['label' => 'Team', 'options' => $teams, 'optionField' => 'name'],
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
                                <div class="space-y-1">

                                    <label for="{{ $field }}"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $config['label'] }}
                                    </label>
                                    <div class="flex items-center space-x-2">
                                        {{-- MODIFICATION: Added disabled attribute if field is verified --}}
                                        <select name="{{ $field }}" id="{{ $field }}" class="form-control"
                                            {{ $verifiedFields[$field] ? 'disabled' : '' }}>
                                            <option value="">-- Select {{ $config['label'] }} --</option>
                                            @foreach ($config['options'] as $option)
                                                <option value="{{ $option->id }}"
                                                    {{ old($field, $player->$field) == $option->id ? 'selected' : '' }}>
                                                    {{ $option->{$config['optionField']} }}
                                                </option>
                                            @endforeach
                                        </select>

                                        @if ($field === 'team_id')
                                            {{-- MODIFICATION: Added disabled attribute if team_id is verified --}}
                                            <input type="text" name="team_name_ref" id="team_name_ref"
                                                placeholder="Enter Team Name"
                                                value="{{ old('team_name_ref', $player->team_name_ref ?? '') }}"
                                                class="form-control w-48"
                                                {{ $verifiedFields['team_id'] ? 'disabled' : '' }}>
                                        @endif
                                        <input type="checkbox" name="verified_{{ $field }}" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_' . $field, $player['verified_' . $field] ?? false) ? 'checked' : '' }}>
                                        <span class="ml-3 text-sm font-bold flex items-center gap-1">
                                            <span
                                                class="{{ $verifiedFields[$field] ? 'text-green-500' : 'text-red-500' }}">
                                                {{ $verifiedFields[$field] ? '✔' : '✖' }}
                                            </span>
                                            <span class="text-gray-800 dark:text-gray-300">
                                                {{ $verifiedFields[$field] ? 'Verified' : 'Not Verified' }}
                                            </span>
                                        </span>


                                    </div>
                                    @error($field)
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach

                            {{-- Player Image Upload --}}
                            <div class="sm:col-span-2">
                                <label for="image_path"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Player Image
                                </label>

                                <div x-data="{
                                    previewUrl: '{{ $player->image_path ? Storage::url($player->image_path) : '' }}',
                                    isVerified: {{ $verifiedFields['image_path'] ? 'true' : 'false' }}, // Alpine variable for verification
                                    handleFileChange(event) {
                                        if (this.isVerified) return; // Prevent change if verified
                                        const file = event.target.files[0];
                                        if (file && file.type.startsWith('image/')) {
                                            this.previewUrl = URL.createObjectURL(file);
                                        } else {
                                            this.previewUrl = '';
                                        }
                                    },
                                    dropHandler(event) {
                                        if (this.isVerified) return; // Prevent drop if verified
                                        event.preventDefault();
                                        const file = event.dataTransfer.files[0];
                                        if (file && file.type.startsWith('image/')) {
                                            this.$refs.fileInput.files = event.dataTransfer.files;
                                            this.previewUrl = URL.createObjectURL(file);
                                        }
                                    }
                                }" @drop.prevent="dropHandler($event)" @dragover.prevent
                                    class="border-2 border-dashed border-gray-300 p-4 rounded-lg text-center relative"
                                    :class="{
                                        'hover:border-blue-500 bg-gray-50 cursor-pointer': !
                                            isVerified,
                                        'bg-gray-200 cursor-not-allowed': isVerified
                                    }"
                                    @click="!isVerified && $refs.fileInput.click()">
                                    {{-- MODIFICATION: Added disabled attribute if field is verified --}}
                                    <input type="file" name="image_path" id="image_path" accept="image/png,image/jpeg"
                                        class="absolute w-0 h-0 opacity-0" x-ref="fileInput" @change="handleFileChange"
                                        {{ $verifiedFields['image_path'] ? 'disabled' : '' }}>

                                    {{-- Image Preview --}}
                                    <template x-if="previewUrl">
                                        <img :src="previewUrl"
                                            class="mx-auto mb-2 h-48 object-contain rounded border border-gray-300" />
                                    </template>

                                    <p x-show="!previewUrl" class="text-gray-600 text-sm">
                                        Drag & drop or click to upload image (PNG/JPG, max 6MB)
                                    </p>
                                    <p x-show="isVerified" class="text-red-600 text-sm font-semibold">
                                        Image is verified and cannot be changed.
                                    </p>
                                </div>

                                {{-- Remove Existing Image --}}
                                @if ($player->image_path)
                                    <label
                                        class="inline-flex items-center mt-2 space-x-2 text-sm text-gray-600 dark:text-gray-300">
                                        {{-- MODIFICATION: Added disabled attribute if field is verified --}}
                                        <input type="checkbox" name="clear_image" value="1"
                                            class="form-checkbox text-red-600 border-gray-300 rounded focus:ring-red-500"
                                            {{ $verifiedFields['image_path'] ? 'disabled' : '' }}>
                                        <span>Remove Existing Image</span>
                                    </label>
                                @endif

                                {{-- Verified Toggle --}}
                                <div class="mt-4">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="verified_image_path" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_image_path', $player->verified_image_path ?? false) ? 'checked' : '' }}>

                                        <span class="ml-3 text-sm font-bold flex items-center gap-1">
                                            <span
                                                class="{{ $verifiedFields[$field] ? 'text-green-500' : 'text-red-500' }}">
                                                {{ $verifiedFields[$field] ? '✔' : '✖' }}
                                            </span>
                                            <span class="text-gray-800 dark:text-gray-300">
                                                {{ $verifiedFields[$field] ? 'Verified' : 'Not Verified' }}
                                            </span>
                                        </span>


                                    </label>
                                </div>
                            </div>

                            <div class="md:col-span-2 flex flex-col gap-4 mt-4" x-data="{
                                noTravel: {{ old('no_travel_plan', $player->no_travel_plan ?? false) ? 'true' : 'false' }},
                                from: '{{ old('travel_date_from', $player->travel_date_from ?? '') }}',
                                to: '{{ old('travel_date_to', $player->travel_date_to ?? '') }}',
                                today: (new Date()).toISOString().split('T')[0]
                            }">
                                <!-- Travel Date Pickers -->
                                <div x-show="!noTravel" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Travel From -->
                                    <div>
                                        <label for="travel_date_from" class="block font-semibold mb-1">Travel Date
                                            From</label>
                                        <input type="date" name="travel_date_from" id="travel_date_from"
                                            x-model="from" :min="today"
                                            class="w-full px-3 py-2 border rounded text-black"
                                            placeholder="Select start date">
                                        @error('travel_date_from')
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Travel To -->
                                    <div>
                                        <label for="travel_date_to" class="block font-semibold mb-1">Travel Date
                                            To</label>
                                        <input type="date" name="travel_date_to" id="travel_date_to" x-model="to"
                                            :min="from || today" class="w-full px-3 py-2 border rounded text-black"
                                            placeholder="Select end date">
                                        @error('travel_date_to')
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
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
                                        {{-- MODIFICATION: Added disabled attribute if field is verified --}}
                                        <input type="checkbox" name="{{ $field }}" value="1"
                                            {{ old($field, $player->$field) ? 'checked' : '' }} class="mr-2"
                                            {{ $verifiedFields[$field] ? 'disabled' : '' }}>
                                        {{ $label }}
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="verified_{{ $field }}" value="1"
                                                class="sr-only peer"
                                                {{ old('verified_' . $field, $player['verified_' . $field] ?? false) ? 'checked' : '' }}>

                                            <span class="ml-3 text-sm font-bold flex items-center gap-1">
                                                <span
                                                    class="{{ $verifiedFields[$field] ? 'text-green-500' : 'text-red-500' }}">
                                                    {{ $verifiedFields[$field] ? '✔' : '✖' }}
                                                </span>
                                                <span class="text-gray-800 dark:text-gray-300">
                                                    {{ $verifiedFields[$field] ? 'Verified' : 'Not Verified' }}
                                                </span>
                                            </span>


                                        </label>
                                    </label>
                                </div>
                            @endforeach

                        </div>
                        <input type="hidden" name="intimate" id="intimate" value="0">
                        <input type="hidden" name="allverified" id="allverified" value="0">

                        {{-- Submit Buttons --}}
                        <div class="mt-6">
                            <x-buttons.submit-buttons cancelUrl="{{ route('admin.dashboard') }}" />
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
