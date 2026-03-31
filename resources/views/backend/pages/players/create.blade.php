@extends('backend.layouts.app')

@section('title')
    {{ $breadcrumbs['title'] }} | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />

        <div class="space-y-6">
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-5 space-y-6 sm:p-6">
                    <form action="{{ route('admin.players.store') }}" method="POST" enctype="multipart/form-data"
                        x-data="{
                            noTravel: {{ old('no_travel_plan') ? 'true' : 'false' }},
                            travelFrom: '{{ old('travel_date_from') }}',
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
                        }">
                        @csrf

                        {{-- ===== Tournament Selector (controls field visibility) ===== --}}
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-1">Tournament Context</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Select a tournament to apply its registration form field config</p>
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="max-w-md">
                                    <label for="tournament_selector" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tournament</label>
                                    <select id="tournament_selector"
                                        class="form-control"
                                        onchange="window.location.href='{{ route('admin.players.create') }}' + (this.value ? '?tournament_id=' + this.value : '')">
                                        <option value="">-- All Fields (No Tournament) --</option>
                                        @foreach($tournaments as $t)
                                            <option value="{{ $t->id }}" {{ ($selectedTournamentId ?? null) == $t->id ? 'selected' : '' }}>
                                                {{ $t->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Changing this reloads the page with the tournament's field visibility settings</p>
                                </div>
                            </div>
                        </div>

                        {{-- ===== SECTION 1: Basic Information ===== --}}
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-1">Basic Information</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Player identity and contact details</p>
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    {{-- Player Name (always visible) --}}
                                    <div class="space-y-1">
                                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Player Name') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="name" id="name" required value="{{ old('name') }}"
                                            placeholder="Enter Player Name"
                                            class="form-control @error('name') border-red-500 @enderror">
                                        @error('name')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Player Email (always visible) --}}
                                    <div class="space-y-1">
                                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Email Address') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input type="email" name="email" id="email" required value="{{ old('email') }}"
                                            placeholder="Enter Player Email"
                                            class="form-control @error('email') border-red-500 @enderror">
                                        @error('email')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Country --}}
                                    @if($fieldConfig['country']['visible'] ?? true)
                                    <div class="space-y-1">
                                        <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Country') }} @if($fieldConfig['country']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                        </label>
                                        <select name="country" id="country" class="form-control @error('country') border-red-500 @enderror"
                                            onchange="updateDialCode(this.value)">
                                            <option value="">-- Select Country --</option>
                                            @foreach (config('countries.list', []) as $code => $name)
                                                <option value="{{ $code }}" {{ old('country', $defaultCountry ?? '') == $code ? 'selected' : '' }}>
                                                    {{ $name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('country')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    @endif

                                    {{-- Location --}}
                                    @if($fieldConfig['location']['visible'] ?? true)
                                    <div class="space-y-1">
                                        <label for="location_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Location') }} @if($fieldConfig['location']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                        </label>
                                        <select name="location_id" id="location_id" class="form-control" {{ ($fieldConfig['location']['required'] ?? false) ? 'required' : '' }}>
                                            <option value="">-- Select Location --</option>
                                            @foreach ($locations as $location)
                                                <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>
                                                    {{ $location->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('location_id')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    @endif

                                    {{-- Mobile Number --}}
                                    @if($fieldConfig['mobile_number']['visible'] ?? true)
                                    <div class="space-y-1">
                                        <label for="mobile_national_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Mobile Number') }} @if($fieldConfig['mobile_number']['required'] ?? true)<span class="text-red-500">*</span>@endif
                                        </label>
                                        <div class="flex items-start gap-2">
                                            <div class="w-2/5">
                                                <select name="mobile_country_code" id="mobile_country_code" {{ ($fieldConfig['mobile_number']['required'] ?? true) ? 'required' : '' }}
                                                    class="form-control @error('mobile_country_code') border-red-500 @enderror">
                                                    @foreach (config('countries.dial_codes', []) as $code => $dial)
                                                        <option value="{{ $dial }}"
                                                            {{ old('mobile_country_code', $defaultDialCode ?? '+971') == $dial ? 'selected' : '' }}>
                                                            {{ config('countries.list.' . $code) }} ({{ $dial }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('mobile_country_code')
                                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="w-3/5">
                                                <input type="text" name="mobile_national_number" id="mobile_national_number"
                                                    {{ ($fieldConfig['mobile_number']['required'] ?? true) ? 'required' : '' }} value="{{ old('mobile_national_number') }}"
                                                    placeholder="Enter Mobile Number"
                                                    class="form-control @error('mobile_national_number') border-red-500 @enderror">
                                                @error('mobile_national_number')
                                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    {{-- Cricheroes Number --}}
                                    @if($fieldConfig['cricheroes_number']['visible'] ?? true)
                                    <div class="space-y-1">
                                        <label for="cricheroes_national_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Cricheroes Number') }} @if($fieldConfig['cricheroes_number']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                        </label>
                                        <div class="flex items-start gap-2">
                                            <div class="w-2/5">
                                                <select name="cricheroes_country_code" id="cricheroes_country_code"
                                                    class="form-control @error('cricheroes_country_code') border-red-500 @enderror">
                                                    @foreach (config('countries.dial_codes', []) as $code => $dial)
                                                        <option value="{{ $dial }}"
                                                            {{ old('cricheroes_country_code', $defaultDialCode ?? '+971') == $dial ? 'selected' : '' }}>
                                                            {{ config('countries.list.' . $code) }} ({{ $dial }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('cricheroes_country_code')
                                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <div class="w-3/5">
                                                <input type="text" name="cricheroes_national_number" id="cricheroes_national_number"
                                                    value="{{ old('cricheroes_national_number') }}"
                                                    placeholder="Enter Cricheroes Number"
                                                    class="form-control @error('cricheroes_national_number') border-red-500 @enderror">
                                                @error('cricheroes_national_number')
                                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    {{-- CricHeroes Profile URL --}}
                                    @if($fieldConfig['cricheroes_profile_url']['visible'] ?? true)
                                    <div class="space-y-1 sm:col-span-2">
                                        <label for="cricheroes_profile_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('CricHeroes Profile URL') }} @if($fieldConfig['cricheroes_profile_url']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                        </label>
                                        <input type="url" name="cricheroes_profile_url" id="cricheroes_profile_url"
                                            value="{{ old('cricheroes_profile_url') }}"
                                            placeholder="https://cricheroes.com/player-profile/..."
                                            class="form-control @error('cricheroes_profile_url') border-red-500 @enderror">
                                        @error('cricheroes_profile_url')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- ===== SECTION 2: Team Assignment ===== --}}
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-1">Team Assignment</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Assign to registration and playing teams</p>
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    {{-- Registration Team --}}
                                    <div class="space-y-1">
                                        <label for="team_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Registration Team') }}
                                            <span class="text-xs text-gray-500">(Original team)</span>
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <select name="team_id" id="team_id"
                                                class="form-control @error('team_id') border-red-500 @enderror">
                                                <option value="">-- Select Registration Team --</option>
                                                @foreach ($teams as $team)
                                                    <option value="{{ $team->id }}"
                                                        {{ old('team_id') == $team->id ? 'selected' : '' }}>
                                                        {{ $team->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <input type="text" name="team_name_ref" id="team_name_ref"
                                                placeholder="Team Name (if Others)"
                                                value="{{ old('team_name_ref') }}"
                                                class="form-control w-48">
                                        </div>
                                        @error('team_id')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Playing Team --}}
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
                                                    {{ old('actual_team_id') == $team->id ? 'selected' : '' }}>
                                                    {{ $team->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('actual_team_id')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ===== SECTION 3: Jersey & Profile ===== --}}
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-1">Jersey & Profile</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Jersey details and playing style</p>
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    {{-- Jersey Name --}}
                                    @if($fieldConfig['jersey_name']['visible'] ?? true)
                                    <div class="space-y-1">
                                        <label for="jersey_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Jersey Name') }} @if($fieldConfig['jersey_name']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                        </label>
                                        <input type="text" name="jersey_name" id="jersey_name"
                                            value="{{ old('jersey_name') }}" placeholder="Name on jersey"
                                            class="form-control @error('jersey_name') border-red-500 @enderror">
                                        @error('jersey_name')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    @endif

                                    {{-- Jersey Number --}}
                                    @if($fieldConfig['jersey_number']['visible'] ?? true)
                                    <div class="space-y-1">
                                        <label for="jersey_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Jersey Number') }} @if($fieldConfig['jersey_number']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                        </label>
                                        <input type="number" name="jersey_number" id="jersey_number"
                                            value="{{ old('jersey_number') }}" placeholder="e.g. 7"
                                            class="form-control @error('jersey_number') border-red-500 @enderror">
                                        @error('jersey_number')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    @endif

                                    {{-- Jersey Size --}}
                                    @if($fieldConfig['kit_size']['visible'] ?? true)
                                    <div class="space-y-1">
                                        <label for="kit_size_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Jersey Size') }} @if($fieldConfig['kit_size']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                        </label>
                                        <select name="kit_size_id" id="kit_size_id"
                                            class="form-control @error('kit_size_id') border-red-500 @enderror">
                                            <option value="">-- Select Jersey Size --</option>
                                            @foreach ($kitSizes as $kit)
                                                <option value="{{ $kit->id }}"
                                                    {{ old('kit_size_id') == $kit->id ? 'selected' : '' }}>
                                                    {{ $kit->size }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('kit_size_id')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    @endif

                                    {{-- Batting Profile --}}
                                    @if($fieldConfig['batting_profile']['visible'] ?? true)
                                    <div class="space-y-1">
                                        <label for="batting_profile_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Batting Profile') }} @if($fieldConfig['batting_profile']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                        </label>
                                        <select name="batting_profile_id" id="batting_profile_id"
                                            class="form-control @error('batting_profile_id') border-red-500 @enderror">
                                            <option value="">-- Select Batting Profile --</option>
                                            @foreach ($battingProfiles as $profile)
                                                <option value="{{ $profile->id }}"
                                                    {{ old('batting_profile_id') == $profile->id ? 'selected' : '' }}>
                                                    {{ $profile->style }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('batting_profile_id')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    @endif

                                    {{-- Bowling Profile --}}
                                    @if($fieldConfig['bowling_profile']['visible'] ?? true)
                                    <div class="space-y-1">
                                        <label for="bowling_profile_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Bowling Profile') }} @if($fieldConfig['bowling_profile']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                        </label>
                                        <select name="bowling_profile_id" id="bowling_profile_id"
                                            class="form-control @error('bowling_profile_id') border-red-500 @enderror">
                                            <option value="">-- Select Bowling Profile --</option>
                                            @foreach ($bowlingProfiles as $profile)
                                                <option value="{{ $profile->id }}"
                                                    {{ old('bowling_profile_id') == $profile->id ? 'selected' : '' }}>
                                                    {{ $profile->style }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('bowling_profile_id')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    @endif

                                    {{-- Player Type --}}
                                    @if($fieldConfig['player_type']['visible'] ?? true)
                                    <div class="space-y-1">
                                        <label for="player_type_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ __('Player Type') }} @if($fieldConfig['player_type']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                        </label>
                                        <select name="player_type_id" id="player_type_id"
                                            class="form-control @error('player_type_id') border-red-500 @enderror">
                                            <option value="">-- Select Player Type --</option>
                                            @foreach ($playerTypes as $type)
                                                <option value="{{ $type->id }}"
                                                    {{ old('player_type_id') == $type->id ? 'selected' : '' }}>
                                                    {{ $type->type }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('player_type_id')
                                            <p class="text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    @endif
                                </div>

                                {{-- Wicket Keeper --}}
                                @if($fieldConfig['is_wicket_keeper']['visible'] ?? true)
                                <div class="mt-4">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="is_wicket_keeper" id="is_wicket_keeper" value="1"
                                            class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                                            {{ old('is_wicket_keeper') ? 'checked' : '' }}>
                                        <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Is Wicket Keeper?') }}</span>
                                    </label>
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- ===== SECTION 4: Leather Ball Experience ===== --}}
                        @if(($fieldConfig['total_matches']['visible'] ?? true) || ($fieldConfig['total_runs']['visible'] ?? true) || ($fieldConfig['total_wickets']['visible'] ?? true))
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-1">Leather Ball Experience</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Career stats (leather ball)</p>
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                                    @foreach ([
                                        'total_matches' => 'Total Matches',
                                        'total_runs' => 'Total Runs',
                                        'total_wickets' => 'Total Wickets',
                                    ] as $field => $label)
                                        @if($fieldConfig[$field]['visible'] ?? true)
                                        <div class="space-y-1">
                                            <label for="{{ $field }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ $label }} @if($fieldConfig[$field]['required'] ?? false)<span class="text-red-500">*</span>@endif
                                            </label>
                                            <input type="number" name="{{ $field }}" id="{{ $field }}"
                                                min="0" value="{{ old($field, 0) }}" class="form-control">
                                            @error($field)
                                                <p class="text-sm text-red-500">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- ===== SECTION 5: Player Image ===== --}}
                        @if($fieldConfig['image']['visible'] ?? true)
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-1">Player Photo</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Upload player profile image</p>
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="max-w-md">
                                    <div @drop.prevent="dropHandler($event)" @dragover.prevent
                                        class="border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-blue-500 bg-gray-50 dark:bg-gray-800/50 p-6 rounded-lg text-center cursor-pointer transition-colors"
                                        @click="$refs.fileInput.click()">
                                        <input type="file" name="image_path" id="image_path" accept="image/png,image/jpeg"
                                            class="absolute w-0 h-0 opacity-0" x-ref="fileInput" @change="handleFileChange">

                                        <template x-if="previewUrl">
                                            <img :src="previewUrl" class="mx-auto mb-3 h-48 object-contain rounded-lg border border-gray-300 dark:border-gray-600" />
                                        </template>

                                        <div x-show="!previewUrl" class="text-gray-500 dark:text-gray-400">
                                            <svg class="w-10 h-10 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <p class="text-sm font-medium">Drag & drop or click to upload</p>
                                            <p class="text-xs mt-1">PNG or JPG (max 6MB)</p>
                                        </div>
                                    </div>
                                    @error('image_path')
                                        <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- ===== SECTION 6: Travel & Transportation ===== --}}
                        @if(($fieldConfig['transportation']['visible'] ?? true) || ($fieldConfig['travel_plan']['visible'] ?? true))
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-1">Travel & Transportation</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Availability and transport needs</p>
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <div class="space-y-4">
                                    {{-- Transportation Required --}}
                                    @if($fieldConfig['transportation']['visible'] ?? true)
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="transportation_required" id="transportation_required"
                                            value="1"
                                            class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                                            {{ old('transportation_required') ? 'checked' : '' }}>
                                        <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Transportation Required?') }}</span>
                                    </label>
                                    @endif

                                    {{-- No Travel Plan --}}
                                    @if($fieldConfig['travel_plan']['visible'] ?? true)
                                    <div>
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="no_travel_plan" value="1" x-model="noTravel"
                                                class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800" />
                                            <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">No Travel Plan (available throughout)</span>
                                        </label>
                                        @error('no_travel_plan')
                                            <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Travel Dates --}}
                                    <div x-show="!noTravel" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4 ml-6">
                                        <div class="space-y-1">
                                            <label for="travel_date_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Travel Date From
                                            </label>
                                            <input type="date" name="travel_date_from" id="travel_date_from"
                                                x-model="travelFrom" :min="new Date().toISOString().split('T')[0]"
                                                value="{{ old('travel_date_from') }}"
                                                class="form-control @error('travel_date_from') border-red-500 @enderror">
                                            @error('travel_date_from')
                                                <p class="text-sm text-red-500">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="space-y-1">
                                            <label for="travel_date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Travel Date To
                                            </label>
                                            <input type="date" name="travel_date_to" id="travel_date_to"
                                                :min="travelFrom || new Date().toISOString().split('T')[0]"
                                                value="{{ old('travel_date_to') }}"
                                                class="form-control @error('travel_date_to') border-red-500 @enderror">
                                            @error('travel_date_to')
                                                <p class="text-sm text-red-500">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- ===== Submit ===== --}}
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                            <x-buttons.submit-buttons cancelUrl="{{ route('admin.players.index') }}" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const dialCodes = @json(config('countries.dial_codes', []));
    function updateDialCode(countryCode) {
        if (dialCodes[countryCode]) {
            const dialCode = dialCodes[countryCode];
            const mobileSelect = document.getElementById('mobile_country_code');
            const cricSelect = document.getElementById('cricheroes_country_code');
            if (mobileSelect) mobileSelect.value = dialCode;
            if (cricSelect) cricSelect.value = dialCode;
        }
    }
</script>
@endpush
