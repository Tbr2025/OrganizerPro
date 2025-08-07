@extends('backend.layouts.app')

@section('title')
    Edit Player | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-screen-xl md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        <div class="space-y-6">
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-5 space-y-6 sm:p-6">
                    <form action="{{ route('admin.players.update', $player->id) }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">

                            @php
                                $fields = [
                                    'name' => 'Player Name',
                                    'email' => 'Email',

                                    'mobile_number_full' => 'Full Mobile Number',
                                    'cricheroes_number_full' => 'Full Cricheroes Number',
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
                                        <input type="text" id="{{ $field }}" name="{{ $field }}"
                                            value="{{ old($field, $player->$field) }}"
                                            class="form-control @error($field) border-red-500 @enderror">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="verified_{{ $field }}" value="1"
                                                class="sr-only peer"
                                                {{ old('verified_' . $field, $player['verified_' . $field] ?? false) ? 'checked' : '' }}>
                                            <div
                                                class="w-11 h-6 bg-gray-300 rounded-full peer peer-focus:ring-2 peer-focus:ring-indigo-400
                dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300">
                                            </div>
                                            <div
                                                class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full
                transition-transform duration-300 peer-checked:translate-x-full">
                                            </div>
                                            <span
                                                class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                        </label>
                                    </div>
                                    @error($field)
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach

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
                                        <select name="{{ $field }}" id="{{ $field }}" class="form-control">
                                            <option value="">-- Select {{ $config['label'] }} --</option>
                                            @foreach ($config['options'] as $option)
                                                <option value="{{ $option->id }}"
                                                    {{ old($field, $player->$field) == $option->id ? 'selected' : '' }}>
                                                    {{ $option->{$config['optionField']} }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="verified_{{ $field }}" value="1"
                                                class="sr-only peer"
                                                {{ old('verified_' . $field, $player['verified_' . $field] ?? false) ? 'checked' : '' }}>
                                            <div
                                                class="w-11 h-6 bg-gray-300 rounded-full peer peer-focus:ring-2 peer-focus:ring-indigo-400
                dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300">
                                            </div>
                                            <div
                                                class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full
                transition-transform duration-300 peer-checked:translate-x-full">
                                            </div>
                                            <span
                                                class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                        </label>
                                    </div>
                                    @error($field)
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach

                            {{-- Image Upload --}}
{{-- Player Image Upload --}}
<div class="sm:col-span-2">
    <label for="image_path" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Player Image
    </label>

    <div 
        x-data="{
            previewUrl: '{{ $player->image_path ? Storage::url($player->image_path) : '' }}',
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
        }"
        @drop.prevent="dropHandler($event)"
        @dragover.prevent
        class="border-2 border-dashed border-gray-300 hover:border-blue-500 bg-gray-50 p-4 rounded-lg text-center cursor-pointer relative"
        @click="$refs.fileInput.click()"
    >
        <input
            type="file"
            name="image_path"
            id="image_path"
            accept="image/png,image/jpeg"
            class="absolute w-0 h-0 opacity-0"
            x-ref="fileInput"
            @change="handleFileChange"
        >

        {{-- Image Preview --}}
        <template x-if="previewUrl">
            <img :src="previewUrl" class="mx-auto mb-2 h-48 object-contain rounded border border-gray-300" />
        </template>

        <p x-show="!previewUrl" class="text-gray-600 text-sm">
            Drag & drop or click to upload image (PNG/JPG, max 2MB)
        </p>
    </div>

    {{-- Remove Existing Image --}}
    @if ($player->image_path)
        <label class="inline-flex items-center mt-2 space-x-2 text-sm text-gray-600 dark:text-gray-300">
            <input type="checkbox" name="clear_image" value="1"
                class="form-checkbox text-red-600 border-gray-300 rounded focus:ring-red-500">
            <span>Remove Existing Image</span>
        </label>
    @endif

    {{-- Verified Toggle --}}
    <div class="mt-4">
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="verified_image_path" value="1"
                class="sr-only peer"
                {{ old('verified_image_path', $player->verified_image_path ?? false) ? 'checked' : '' }}>
            <div
                class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300">
            </div>
            <div
                class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full">
            </div>
            <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
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
                                        <input type="date" name="travel_date_from" id="travel_date_from" x-model="from"
                                            :min="today" class="w-full px-3 py-2 border rounded text-black"
                                            placeholder="Select start date">
                                        @error('travel_date_from')
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Travel To -->
                                    <div>
                                        <label for="travel_date_to" class="block font-semibold mb-1">Travel Date To</label>
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
                                        <input type="checkbox" name="{{ $field }}" value="1"
                                            {{ old($field, $player->$field) ? 'checked' : '' }} class="mr-2">
                                        {{ $label }}
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="verified_{{ $field }}" value="1"
                                                class="sr-only peer"
                                                {{ old('verified_' . $field, $player['verified_' . $field] ?? false) ? 'checked' : '' }}>
                                            <div
                                                class="w-11 h-6 bg-gray-300 rounded-full peer peer-focus:ring-2 peer-focus:ring-indigo-400
                dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300">
                                            </div>
                                            <div
                                                class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full
                transition-transform duration-300 peer-checked:translate-x-full">
                                            </div>
                                            <span
                                                class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                        </label>
                                    </label>
                                </div>
                            @endforeach

                        </div>
                        <input type="hidden" name="intimate" id="intimate" value="0">
                        <input type="hidden" name="allverified" id="allverified" value="0">

                        {{-- All Fields Verified --}}
                        {{-- Submit Buttons --}}
                        <div class="mt-6">
                            <x-buttons.submit-buttons cancelUrl="{{ route('admin.players.index') }}" />
                        </div>
                        <div class="mt-5">

                            <button type="submit" onclick="document.getElementById('intimate').value = 1;"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Intimate Player
                            </button>

                            @if ($templates->count() > 0)
                                <button type="submit" onclick="document.getElementById('allverified').value = 1;"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Welcome Player - Generate Image
                                </button>
                            @else
                                <button type="button" disabled
                                    class="inline-flex items-center px-4 py-2 bg-gray-400 text-white text-sm font-medium rounded-md shadow-sm cursor-not-allowed">
                                    Welcome Player - Generate Image
                                </button>
                                <p class="text-sm text-red-600 mt-2">
                                    ⚠️ No welcome image template found.
                                    <a href="{{ route('admin.image-templates.create') }}"
                                        class="underline text-blue-600 hover:text-blue-800">
                                        Create one now.
                                    </a>
                                </p>
                            @endif

                        </div>


                    </form>



                </div>
            </div>
        </div>
    </div>
@endsection
