@extends('backend.layouts.app')

@section('title')
    Edit Player | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto  md:p-6">
        <div class="flex justify-between items-center mb-4">
            <x-breadcrumbs :breadcrumbs="$breadcrumbs" />
            @can('player.delete')
                <form action="{{ route('admin.players.destroy', $player->id) }}" method="POST"
                    onsubmit="return confirm('Are you sure you want to delete this player? This action cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Delete Player
                    </button>
                </form>
            @endcan
        </div>

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
                                    'jersey_name' => 'Jersey Name',
                                    'jersey_number' => 'Jersey Number',
                                ];
                            @endphp

                            {{-- Country --}}
                            <div class="space-y-1">
                                <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Country') }} @if($fieldConfig['country']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                <div class="flex items-center space-x-2">
                                    <select name="country" id="country" class="form-control @error('country') border-red-500 @enderror">
                                        <option value="">-- Select Country --</option>
                                        @foreach (config('countries.list', []) as $code => $name)
                                            <option value="{{ $code }}" {{ old('country', $player->country) == $code ? 'selected' : '' }}>
                                                {{ $name }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @php
                                        $canVerify = auth()->user()->hasAnyRole(['Superadmin', 'Admin']);
                                    @endphp
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_country" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_country', $player->verified_country ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400
                dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full
                transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('country')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Mobile Number with Country Code Dropdown --}}
                            <div class="space-y-1">
                                <label for="mobile_number_full" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Mobile Number') }} @if($fieldConfig['mobile_number']['required'] ?? true)<span class="text-red-500">*</span>@endif
                                </label>
                                <div class="flex items-center space-x-2">
                                    <div class="flex items-start gap-2 flex-1">
                                        <div class="w-2/5">
                                            <select name="mobile_country_code_display" id="mobile_country_code_display"
                                                class="form-control"
                                                onchange="updateMobileFullNumber()">
                                                @foreach (config('countries.dial_codes', []) as $code => $dial)
                                                    <option value="{{ $dial }}"
                                                        {{ old('mobile_country_code', $player->mobile_country_code ?? '') == $dial ? 'selected' : '' }}>
                                                        {{ config('countries.list.' . $code) }} ({{ $dial }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="w-3/5">
                                            <input type="text" id="mobile_national_display"
                                                value="{{ old('mobile_national_number', $player->mobile_national_number ?? $player->mobile_number_full) }}"
                                                placeholder="Enter Mobile Number"
                                                class="form-control"
                                                oninput="updateMobileFullNumber()">
                                        </div>
                                    </div>
                                    <input type="hidden" name="mobile_number_full" id="mobile_number_full"
                                        value="{{ old('mobile_number_full', $player->mobile_number_full) }}">

                                    @php $canVerify = auth()->user()->hasAnyRole(['Superadmin', 'Admin']); @endphp
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_mobile_number_full" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_mobile_number_full', $player->verified_mobile_number_full ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400
                dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full
                transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('mobile_number_full')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Cricheroes Number with Country Code Dropdown --}}
                            <div class="space-y-1">
                                <label for="cricheroes_number_full" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Cricheroes Number') }} @if($fieldConfig['cricheroes_number']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                <div class="flex items-center space-x-2">
                                    <div class="flex items-start gap-2 flex-1">
                                        <div class="w-2/5">
                                            <select name="cricheroes_country_code_display" id="cricheroes_country_code_display"
                                                class="form-control"
                                                onchange="updateCricheroesFullNumber()">
                                                @foreach (config('countries.dial_codes', []) as $code => $dial)
                                                    <option value="{{ $dial }}"
                                                        {{ old('cricheroes_country_code', $player->cricheroes_country_code ?? '') == $dial ? 'selected' : '' }}>
                                                        {{ config('countries.list.' . $code) }} ({{ $dial }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="w-3/5">
                                            <input type="text" id="cricheroes_national_display"
                                                value="{{ old('cricheroes_national_number', $player->cricheroes_national_number ?? $player->cricheroes_number_full) }}"
                                                placeholder="Enter Cricheroes Number"
                                                class="form-control"
                                                oninput="updateCricheroesFullNumber()">
                                        </div>
                                    </div>
                                    <input type="hidden" name="cricheroes_number_full" id="cricheroes_number_full"
                                        value="{{ old('cricheroes_number_full', $player->cricheroes_number_full) }}">

                                    @php $canVerify = auth()->user()->hasAnyRole(['Superadmin', 'Admin']); @endphp
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_cricheroes_number_full" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_cricheroes_number_full', $player->verified_cricheroes_number_full ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400
                dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full
                transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('cricheroes_number_full')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- CricHeroes Profile URL --}}
                            <div class="space-y-1 sm:col-span-2">
                                <label for="cricheroes_profile_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('CricHeroes Profile URL') }} @if($fieldConfig['cricheroes_profile_url']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input type="url" name="cricheroes_profile_url" id="cricheroes_profile_url"
                                        value="{{ old('cricheroes_profile_url', $player->cricheroes_profile_url) }}"
                                        placeholder="https://cricheroes.com/player-profile/..."
                                        class="form-control @error('cricheroes_profile_url') border-red-500 @enderror">

                                    @php $canVerify = auth()->user()->hasAnyRole(['Superadmin', 'Admin']); @endphp
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_cricheroes_profile_url" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_cricheroes_profile_url', $player->verified_cricheroes_profile_url ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400
                dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full
                transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('cricheroes_profile_url')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Basic Inputs --}}
                            @foreach ($fields as $field => $label)
                                <div class="space-y-1">
                                    <label for="{{ $field }}"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $label }} @if($fieldConfig[$field]['required'] ?? false)<span class="text-red-500">*</span>@endif
                                    </label>
                                    <div class="flex items-center space-x-2">
                                        <input type="text" id="{{ $field }}" name="{{ $field }}"
                                            value="{{ old($field, $player->$field) }}"
                                            class="form-control @error($field) border-red-500 @enderror">

                                        @php
                                            $canVerify = auth()
                                                ->user()
                                                ->hasAnyRole(['Superadmin', 'Admin']);
                                        @endphp
                                        <label
                                            class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                            <input type="checkbox" name="verified_{{ $field }}" value="1"
                                                class="sr-only peer"
                                                {{ old('verified_' . $field, $player['verified_' . $field] ?? false) ? 'checked' : '' }}
                                                @unless ($canVerify) disabled @endunless>
                                            <div
                                                class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400
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
                                    Player Location @if($fieldConfig['location']['required'] ?? false)<span class="text-red-500">*</span>@endif
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
                                    'team_id' => ['label' => 'Registration Team', 'options' => $teams, 'optionField' => 'name'],
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

                            @php
                                $fieldConfigMap = [
                                    'team_id' => 'team',
                                    'kit_size_id' => 'kit_size',
                                    'batting_profile_id' => 'batting_profile',
                                    'bowling_profile_id' => 'bowling_profile',
                                    'player_type_id' => 'player_type',
                                ];
                            @endphp

                            @foreach ($dropdowns as $field => $config)
                                <div class="space-y-1">

                                    <label for="{{ $field }}"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $config['label'] }}
                                        @if ($field === 'team_id')
                                            <span class="text-xs text-gray-500">(Original team)</span>
                                        @endif
                                        @if($fieldConfig[$fieldConfigMap[$field] ?? $field]['required'] ?? false)<span class="text-red-500">*</span>@endif
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

                                        @if ($field === 'team_id')
                                            <input type="text" name="team_name_ref" id="team_name_ref"
                                                placeholder="Enter Team Name (if Others)"
                                                value="{{ old('team_name_ref', $player->team_name_ref ?? '') }}"
                                                class="form-control w-48">
                                        @endif
                                        @php
                                            $canVerify = auth()
                                                ->user()
                                                ->hasAnyRole(['Superadmin', 'Admin']);
                                        @endphp
                                        <label
                                            class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                            <input type="checkbox" name="verified_{{ $field }}" value="1"
                                                class="sr-only peer"
                                                {{ old('verified_' . $field, $player['verified_' . $field] ?? false) ? 'checked' : '' }}
                                                @unless ($canVerify) disabled @endunless>
                                            <div
                                                class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400
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

                            {{-- Playing Team (Actual Team) --}}
                            <div class="space-y-1">
                                <label for="actual_team_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Playing Team') }}
                                    <span class="text-xs text-gray-500">(Current team)</span>
                                </label>
                                <select name="actual_team_id" id="actual_team_id"
                                    class="form-control @error('actual_team_id') border-red-500 @enderror">
                                    <option value="">-- Select Playing Team --</option>
                                    @foreach ($actualTeams as $team)
                                        <option value="{{ $team->id }}"
                                            {{ old('actual_team_id', $player->actual_team_id) == $team->id ? 'selected' : '' }}>
                                            {{ $team->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('actual_team_id')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Image Upload --}}
                            {{-- Player Image Upload --}}

                            <div class="sm:col-span-2">
                                <label for="image_path"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Player Image
                                </label>
                                <div class="flex items-center gap-1">
                                    <span>{{ $player->name }}</span>
                                    @if ($verifiedProfile)
                                        <svg class="w-4 h-4 text-blue-500" xmlns="http://www.w3.org/2000/svg"
                                            fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </div>

                                <div x-data="{
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
                                }" @drop.prevent="dropHandler($event)" @dragover.prevent
                                    class="border-2 border-dashed border-gray-300 hover:border-blue-500 bg-gray-50 p-4 rounded-lg text-center cursor-pointer relative"
                                    @click="$refs.fileInput.click()">
                                    <input type="file" name="image_path" id="image_path" accept="image/png,image/jpeg"
                                        class="absolute w-0 h-0 opacity-0" x-ref="fileInput" @change="handleFileChange">

                                    {{-- Image Preview --}}
                                    <template x-if="previewUrl">
                                        <img :src="previewUrl"
                                            class="mx-auto mb-2 h-48 object-contain rounded border border-gray-300" />
                                    </template>

                                    <p x-show="!previewUrl" class="text-gray-600 text-sm">
                                        Drag & drop or click to upload image (PNG/JPG, max 6MB)
                                    </p>
                                </div>

                                {{-- Remove Existing Image --}}
                                @if ($player->image_path)
                                    <label
                                        class="inline-flex items-center mt-2 space-x-2 text-sm text-gray-600 dark:text-gray-300">
                                        <input type="checkbox" name="clear_image" value="1"
                                            class="form-checkbox text-red-600 border-gray-300 rounded focus:ring-red-500">
                                        <span>Remove Existing Image</span>
                                    </label>
                                @endif

                                {{-- Verified Toggle --}}
                                <div class="mt-4">
                                    @php
                                        $canVerify = auth()
                                            ->user()
                                            ->hasAnyRole(['Superadmin', 'Admin']);
                                    @endphp

                                    <label
                                        class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_image_path" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_image_path', $player->verified_image_path ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div
                                            class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400
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

                                        {{-- Verified toggle --}}
                                        @php
                                            $canVerify = auth()
                                                ->user()
                                                ->hasAnyRole(['Superadmin', 'Admin']);
                                        @endphp

                                        <label
                                            class="relative inline-flex items-center ml-3 {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                            <input type="checkbox" name="verified_{{ $field }}" value="1"
                                                class="sr-only peer"
                                                {{ old('verified_' . $field, $player['verified_' . $field] ?? false) ? 'checked' : '' }}
                                                @unless ($canVerify) disabled @endunless>
                                            <div
                                                class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400
               dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300">
                                            </div>
                                            <div
                                                class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full
               transition-transform duration-300 peer-checked:translate-x-full">
                                            </div>
                                            <span
                                                class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                        </label>


                                        {{-- Extra date pickers for "No Travel Plan" --}}
                                        {{-- Extra date pickers for "No Travel Plan" --}}
                                        @if ($field === 'no_travel_plan')
                                            <div class="mt-4 grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="text-xs text-gray-500">From Date</label>
                                                    <input type="date" id="travel_date_from" name="travel_date_from"
                                                        value="{{ old('travel_date_from', $player->travel_date_from ? \Carbon\Carbon::parse($player->travel_date_from)->format('Y-m-d') : '') }}"
                                                        class="border-gray-300 rounded-md shadow-sm w-full js-single-datepicker"
                                                        placeholder="YYYY-MM-DD">
                                                </div>

                                                <div>
                                                    <label class="text-xs text-gray-500">To Date</label>
                                                    <input type="date" id="travel_date_to" name="travel_date_to"
                                                        value="{{ old('travel_date_to', $player->travel_date_to ? \Carbon\Carbon::parse($player->travel_date_to)->format('Y-m-d') : '') }}"
                                                        class="border-gray-300 rounded-md shadow-sm w-full js-single-datepicker"
                                                        placeholder="YYYY-MM-DD">
                                                </div>
                                            </div>
                                        @endif

                                </div>
                            @endforeach

                        </div>



                        <input type="hidden" name="intimate" id="intimate" value="0">


                        {{-- All Fields Verified --}}
                        {{-- Submit Buttons --}}
                        <div class="mt-6">
                            <x-buttons.submit-buttons cancelUrl="{{ route('admin.players.index') }}" />
                        </div>
                        <div class="mt-5 mb-5">

                            <button type="submit" onclick="document.getElementById('intimate').value = 1;"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Intimate Player
                            </button>


                            @if ($templates->count() > 0)
                                @if ($verifiedProfile)
                                    <input type="hidden" name="allverified" value="1">

                                    <button type="submit"
                                        onclick="document.getElementById('allverified').value = '{{ $verifiedProfile }}';"
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Welcome Player - Generate Image
                                    </button>
                                @endif
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
                            @if (!$player->isApproved())
                                <input type="hidden" name="isapproved" value="1">

                                <button type="submit"
                                    onclick="document.getElementById('isApproved').value = '{{ $player->isApproved() }}';"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Approve
                                </button>
                            @else
                                <span class="text-gray-500">Player already approved</span>
                            @endif
                        </div>
                        <div
                            class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-blue-800 text-sm space-y-2 col-span-2">
                            <div class="flex items-start">
                                <span class="material-icons text-blue-400 mr-2">info</span>
                                <p>
                                    <strong>Intimate Player:</strong> Sends an email to the player listing all missing
                                    or unverified details.
                                </p>
                            </div>
                            <div class="flex items-start">
                                <span class="material-icons text-blue-400 mr-2">info</span>
                                <p>
                                    <strong>Welcome Player - Generate Image:</strong> Creates a welcome image using the
                                    selected template and sends it via email.Need to verify all the details to send
                                    welcome message.
                                </p>
                            </div>
                            <div class="flex items-start">
                                <span class="material-icons text-blue-400 mr-2">info</span>
                                <p>
                                    <strong>Active Player</strong> So that player can etit their information from thier
                                    profile page.
                                </p>
                            </div>
                        </div>


                    </form>



                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function updateMobileFullNumber() {
        const code = document.getElementById('mobile_country_code_display').value.replace('+', '');
        const number = document.getElementById('mobile_national_display').value.replace(/\D/g, '');
        document.getElementById('mobile_number_full').value = code + number;
    }
    function updateCricheroesFullNumber() {
        const code = document.getElementById('cricheroes_country_code_display').value.replace('+', '');
        const number = document.getElementById('cricheroes_national_display').value.replace(/\D/g, '');
        document.getElementById('cricheroes_number_full').value = code + number;
    }
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateMobileFullNumber();
        updateCricheroesFullNumber();
    });
</script>
@endpush
