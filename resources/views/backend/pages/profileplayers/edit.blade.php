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

                        @if(session('info'))
                            <div class="mb-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900/40 text-blue-700 dark:text-blue-300 px-4 py-3 text-sm">{{ session('info') }}</div>
                        @endif

                        @if(($registrations?->count() ?? 0) === 0)
                            <div class="mb-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 text-amber-700 dark:text-amber-300 px-4 py-3 text-sm">
                                You are not registered for any tournament yet, so profile edits can't be submitted.
                            </div>
                        @else
                            {{-- Edits are scoped to a tournament and approved by that tournament's admin --}}
                            <div class="mb-5">
                                <label for="registration_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tournament this update is for <span class="text-red-500">*</span></label>
                                <select name="registration_id" id="registration_id" required class="form-control"
                                    onchange="window.location='{{ route('profileplayers.edit') }}?registration_id=' + this.value">
                                    @foreach($registrations as $reg)
                                        <option value="{{ $reg->id }}" {{ ($selectedRegistration?->id === $reg->id) ? 'selected' : '' }}>
                                            {{ $reg->tournament->name ?? 'Tournament #' . $reg->tournament_id }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Your changes will be sent to this tournament's admin for approval before they reflect on your profile.</p>
                            </div>

                            @if(!empty($selectedRegistration?->pending_changes))
                                <div class="mb-5 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900/40 text-amber-800 dark:text-amber-300 px-4 py-3 text-sm">
                                    <strong>Changes pending approval.</strong> You submitted updates for
                                    <strong>{{ $selectedRegistration->tournament->name ?? 'this tournament' }}</strong>
                                    @if($selectedRegistration->pending_changes_submitted_at) on {{ $selectedRegistration->pending_changes_submitted_at->format('d M Y, H:i') }}@endif.
                                    They will reflect on your profile once an admin approves them.
                                </div>
                            @endif

                            @if($isLocked ?? false)
                                <div class="mb-5 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-300 dark:border-green-800 text-green-800 dark:text-green-300 px-4 py-3 text-sm">
                                    <strong>✔ Your registration has been accepted</strong> for
                                    <strong>{{ $selectedRegistration->tournament->name ?? 'this tournament' }}</strong>.
                                    These details are now locked. To update your contact email or password, use your
                                    <a href="{{ route('profile.edit') }}" class="underline font-medium">Account settings</a>.
                                </div>
                            @endif
                        @endif

                        <fieldset @if($isLocked ?? false) disabled @endif>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">

                            @php
                                $fields = [
                                    'name' => 'Player Name',
                                    'email' => 'Email',
                                    'mobile_number_full' => 'Full Mobile Number (With Country Code without +)',
                                    'cricheroes_number_full' => 'Full Cricheroes Number (With Country Code without +)',
                                    'cricheroes_profile_url' => 'CricHeroes Profile URL',
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

                            {{-- T-Shirt & Pant size (admin-managed lists, stored as strings) --}}
                            @php
                                $sizeSelects = [
                                    'tshirt_size' => ['label' => 'T-Shirt Size', 'options' => \App\Helpers\PlayerFormConfig::sizeOptions('tshirt_sizes', \App\Helpers\PlayerFormConfig::defaultTshirtSizes())],
                                    'pant_size'   => ['label' => 'Pant Size', 'options' => \App\Helpers\PlayerFormConfig::sizeOptions('pant_sizes', \App\Helpers\PlayerFormConfig::defaultPantSizes())],
                                ];
                            @endphp
                            @foreach ($sizeSelects as $field => $config)
                                <div class="space-y-1">
                                    <label for="{{ $field }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $config['label'] }}</label>
                                    <select name="{{ $field }}" id="{{ $field }}" class="form-control">
                                        <option value="">-- Select {{ $config['label'] }} --</option>
                                        @foreach ($config['options'] as $opt)
                                            <option value="{{ $opt }}" {{ old($field, $player->$field) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                    @error($field)<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                </div>
                            @endforeach

                            {{-- Player Image Upload --}}
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Player Image
                                </label>

                                <x-player-image-upload name="image_path" :existing-image="$player->image_path" :is-verified="$verifiedFields['image_path'] ?? false" />

                                {{-- Remove Existing Image --}}
                                @if ($player->image_path)
                                    <label class="inline-flex items-center mt-2 space-x-2 text-sm text-gray-600 dark:text-gray-300">
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
                                            <span class="{{ ($verifiedFields['image_path'] ?? false) ? 'text-green-500' : 'text-red-500' }}">
                                                {{ ($verifiedFields['image_path'] ?? false) ? '✔' : '✖' }}
                                            </span>
                                            <span class="text-gray-800 dark:text-gray-300">
                                                {{ ($verifiedFields['image_path'] ?? false) ? 'Verified' : 'Not Verified' }}
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

                        </fieldset>

                        {{-- Submit Buttons (hidden once the registration is accepted) --}}
                        @unless($isLocked ?? false)
                        <div class="mt-6">
                            <x-buttons.submit-buttons cancelUrl="{{ route('admin.dashboard') }}" />
                        </div>
                        @endunless

                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
