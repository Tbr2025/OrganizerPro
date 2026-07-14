@extends('backend.layouts.app')

@section('title', 'Register as Player | ' . config('app.name'))

@section('admin-content')
    <div class="p-4 mx-auto md:p-6">
        <x-breadcrumbs :breadcrumbs="$breadcrumbs" />
        <div class="mb-6">
            <a href="{{ route('team-manager.dashboard') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 flex items-center gap-1 mb-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Dashboard
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Register as Player</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Register yourself as a player for {{ $team->name }}</p>
        </div>

        <div class="space-y-6">
            <div class="rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="p-5 space-y-6 sm:p-6">
                    <form action="{{ route('team-manager.register-as-player.store') }}" method="POST" enctype="multipart/form-data"
                          x-data="{
                              visaStatus: @js(old('visa_status', '')),
                              hasTravelPlan: @js(old('has_travel_plan', '')),
                              selectedCountry: @js(old('country', ($defaultCountry ?: 'IN'))),
                              stateValue: @js(old('state', '')),
                              statesByCountry: @js(config('registration.states_by_country')),
                              dialCodesMap: @js(config('countries.dial_codes')),
                              dialCode: @js(old('mobile_country_code', config('countries.dial_codes')[$defaultPhoneCountry ?? 'IN'] ?? '+91')),
                              cricDialCode: @js(old('cricheroes_country_code', config('countries.dial_codes')[$defaultPhoneCountry ?? 'IN'] ?? '+91')),
                              selectedPositions: @js(old('preferred_batting_positions', [])),
                              get hasStates() { return Array.isArray(this.statesByCountry[this.selectedCountry]) && this.statesByCountry[this.selectedCountry].length > 0; },
                          }">
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

                        {{-- Tournament Checkboxes --}}
                        @if($effectiveTournaments->count() > 0)
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Register for Tournaments
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($effectiveTournaments as $tournament)
                                    <label class="flex items-center gap-2 p-2 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
                                        <input type="checkbox" name="tournament_ids[]" value="{{ $tournament->id }}"
                                            {{ in_array($tournament->id, old('tournament_ids', [])) ? 'checked' : '' }}
                                            {{ $effectiveTournaments->count() === 1 ? 'checked' : '' }}
                                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $tournament->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('tournament_ids')
                                <p class="text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        @endif

                        @foreach($layout as $section)
                            @php
                                // Skip registration_team and playing_team fields (auto-assigned)
                                $sectionFields = array_filter($section['fields'], fn($k) => !in_array($k, ['registration_team', 'playing_team']));
                                if (empty($sectionFields)) continue;
                            @endphp

                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                    {{ $section['title'] }}
                                </h3>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    @foreach($sectionFields as $key)
                                        @php
                                            $cfg = $fieldConfig[$key] ?? ['label' => $key, 'required' => false];
                                            $label = $cfg['label'] ?? $key;
                                            $required = $cfg['required'] ?? false;
                                            $reqMark = $required ? '<span class="text-red-500">*</span>' : '';
                                            $fullWidth = in_array($key, [
                                                'cricheroes_profile_url', 'employer_address',
                                                'played_ys_ipl_s1', 'is_wicket_keeper', 'transportation', 'travel_plan',
                                                'image', 'terms_and_conditions', 'preferred_batting_position',
                                            ], true);
                                            $employerField = in_array($key, ['employer_name', 'employer_position', 'employer_address'], true);
                                            $visitVisaField = $key === 'visa_expiry';
                                        @endphp

                                        <div class="{{ $fullWidth ? 'sm:col-span-2' : '' }} space-y-1"
                                             @if($employerField) x-show="visaStatus === 'work_visa'" x-cloak
                                             @elseif($visitVisaField) x-show="visaStatus === 'visit_visa'" x-cloak @endif>

                                        @switch($key)
                                            @case('first_name')
                                                @php $prefillFirst = explode(' ', $user->name, 2)[0] ?? ''; @endphp
                                                <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <input type="text" id="first_name" value="{{ $prefillFirst }}"
                                                    disabled class="form-control bg-gray-100 dark:bg-gray-700 cursor-not-allowed">
                                                <input type="hidden" name="first_name" value="{{ $prefillFirst }}">
                                                @error('first_name')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('last_name')
                                                @php $prefillLast = implode(' ', array_slice(explode(' ', $user->name), 1)) ?: ''; @endphp
                                                <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <input type="text" id="last_name" value="{{ $prefillLast }}"
                                                    disabled class="form-control bg-gray-100 dark:bg-gray-700 cursor-not-allowed">
                                                <input type="hidden" name="last_name" value="{{ $prefillLast }}">
                                                @error('last_name')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('email')
                                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <input type="email" id="email" value="{{ $user->email }}"
                                                    disabled class="form-control bg-gray-100 dark:bg-gray-700 cursor-not-allowed">
                                                <input type="hidden" name="email" value="{{ $user->email }}">
                                                @error('email')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('date_of_birth')
                                                @php
                                                    $minAge = $tournamentSettings->min_age ?? null;
                                                    $maxAge = $tournamentSettings->max_age ?? null;
                                                    $dobMax = $minAge ? now()->subYears((int) $minAge)->toDateString() : now()->toDateString();
                                                    $dobMin = $maxAge ? now()->subYears((int) $maxAge)->toDateString() : null;
                                                @endphp
                                                <label for="date_of_birth" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <input type="date" name="date_of_birth" id="date_of_birth" value="{{ old('date_of_birth') }}"
                                                    max="{{ $dobMax }}" @if($dobMin) min="{{ $dobMin }}" @endif
                                                    {{ $required ? 'required' : '' }} class="form-control @error('date_of_birth') border-red-500 @enderror">
                                                @if($minAge || $maxAge)
                                                    <p class="text-xs text-gray-500">
                                                        @if($minAge && $maxAge) Age must be between {{ $minAge }} and {{ $maxAge }} years.
                                                        @elseif($minAge) Must be at least {{ $minAge }} years old.
                                                        @else Must be at most {{ $maxAge }} years old. @endif
                                                    </p>
                                                @endif
                                                @error('date_of_birth')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('mobile_number')
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <div class="flex gap-2">
                                                    <select name="mobile_country_code" class="form-control" style="flex:0 0 7rem;" x-model="dialCode">
                                                        @foreach(config('countries.dial_codes', []) as $code => $dial)
                                                            <option value="{{ $dial }}">{{ $dial }} ({{ $code }})</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="tel" name="mobile_national_number" class="form-control flex-1"
                                                        placeholder="501234567" value="{{ old('mobile_national_number') }}" {{ $required ? 'required' : '' }}>
                                                </div>
                                                @error('mobile_country_code')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @error('mobile_national_number')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('cricheroes_number')
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <div class="flex gap-2">
                                                    <select name="cricheroes_country_code" class="form-control" style="flex:0 0 7rem;" x-model="cricDialCode">
                                                        @foreach(config('countries.dial_codes', []) as $code => $dial)
                                                            <option value="{{ $dial }}">{{ $dial }} ({{ $code }})</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="tel" name="cricheroes_national_number" class="form-control flex-1"
                                                        placeholder="501234567" value="{{ old('cricheroes_national_number') }}">
                                                </div>
                                                @error('cricheroes_country_code')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @error('cricheroes_national_number')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('cricheroes_profile_url')
                                                <label for="cricheroes_profile_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <input type="url" name="cricheroes_profile_url" id="cricheroes_profile_url" value="{{ old('cricheroes_profile_url') }}"
                                                    placeholder="https://cricheroes.com/player-profile/..." class="form-control @error('cricheroes_profile_url') border-red-500 @enderror">
                                                @error('cricheroes_profile_url')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('country')
                                                <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <select name="country" id="country" class="form-control @error('country') border-red-500 @enderror" x-model="selectedCountry" {{ $required ? 'required' : '' }}>
                                                    <option value="">-- Select Country --</option>
                                                    @foreach (config('countries.list', []) as $code => $name)
                                                        <option value="{{ $code }}">{{ $name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('country')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('state')
                                                <label for="state" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <select name="state" id="state" class="form-control" x-model="stateValue" x-show="hasStates" :disabled="!hasStates" {{ $required ? 'required' : '' }}>
                                                    <option value="">Select state</option>
                                                    <template x-for="s in (statesByCountry[selectedCountry] || [])" :key="s">
                                                        <option :value="s" x-text="s"></option>
                                                    </template>
                                                </select>
                                                <input type="text" name="state" class="form-control" x-model="stateValue" x-show="!hasStates" :disabled="hasStates" placeholder="Enter state / province" {{ $required ? 'required' : '' }}>
                                                @error('state')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('location')
                                                @if($locations->count() > 0)
                                                <label for="location_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <select name="location_id" id="location_id" class="form-control">
                                                    <option value="">-- Select Location --</option>
                                                    @foreach ($locations as $location)
                                                        <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error('location_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @endif
                                                @break

                                            @case('visa_status')
                                                <label for="visa_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <select name="visa_status" id="visa_status" class="form-control" x-model="visaStatus" {{ $required ? 'required' : '' }}>
                                                    <option value="">Select visa status</option>
                                                    @foreach(config('registration.visa_statuses', []) as $val => $vlabel)
                                                        <option value="{{ $val }}" {{ old('visa_status') === $val ? 'selected' : '' }}>{{ $vlabel }}</option>
                                                    @endforeach
                                                </select>
                                                @error('visa_status')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('visa_expiry')
                                                <label for="visa_expiry" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} @if($required)<span class="text-red-500" x-show="visaStatus === 'visit_visa'">*</span>@endif</label>
                                                <input type="date" name="visa_expiry" id="visa_expiry" value="{{ old('visa_expiry') }}" class="form-control"
                                                    @if($required) x-bind:required="visaStatus === 'visit_visa'" @endif>
                                                @error('visa_expiry')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('employer_name')
                                            @case('employer_position')
                                                <label for="{{ $key }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} @if($required)<span class="text-red-500" x-show="visaStatus === 'work_visa'">*</span>@endif</label>
                                                <input type="text" name="{{ $key }}" id="{{ $key }}" value="{{ old($key) }}" class="form-control" placeholder="{{ $label }}"
                                                    @if($required) x-bind:required="visaStatus === 'work_visa'" @endif>
                                                @error($key)<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('employer_address')
                                                <label for="employer_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} @if($required)<span class="text-red-500" x-show="visaStatus === 'work_visa'">*</span>@endif</label>
                                                <textarea name="employer_address" id="employer_address" rows="2" class="form-control" placeholder="Office address"
                                                    @if($required) x-bind:required="visaStatus === 'work_visa'" @endif>{{ old('employer_address') }}</textarea>
                                                @error('employer_address')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('available_saturday')
                                            @case('available_sunday')
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <div class="flex gap-3">
                                                    <label class="flex items-center gap-2 p-2 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer flex-1">
                                                        <input type="radio" name="{{ $key }}" value="1" {{ (string) old($key) === '1' ? 'checked' : '' }} {{ $required ? 'required' : '' }}>
                                                        <span class="text-sm">Yes</span>
                                                    </label>
                                                    <label class="flex items-center gap-2 p-2 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer flex-1">
                                                        <input type="radio" name="{{ $key }}" value="0" {{ (string) old($key) === '0' ? 'checked' : '' }} {{ $required ? 'required' : '' }}>
                                                        <span class="text-sm">No</span>
                                                    </label>
                                                </div>
                                                @error($key)<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('played_ys_ipl_s1')
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <div class="flex gap-3">
                                                    <label class="flex items-center gap-2 p-2 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer flex-1">
                                                        <input type="radio" name="played_ys_ipl_s1" value="1" {{ old('played_ys_ipl_s1') === '1' ? 'checked' : '' }}>
                                                        <span class="text-sm">Yes</span>
                                                    </label>
                                                    <label class="flex items-center gap-2 p-2 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer flex-1">
                                                        <input type="radio" name="played_ys_ipl_s1" value="0" {{ old('played_ys_ipl_s1') === '0' ? 'checked' : '' }}>
                                                        <span class="text-sm">No</span>
                                                    </label>
                                                </div>
                                                @error('played_ys_ipl_s1')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('jersey_name')
                                                <label for="jersey_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <input type="text" name="jersey_name" id="jersey_name" value="{{ old('jersey_name') }}" {{ $required ? 'required' : '' }}
                                                    class="form-control @error('jersey_name') border-red-500 @enderror" placeholder="Name on jersey">
                                                @error('jersey_name')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('jersey_number')
                                                <label for="jersey_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <input type="number" name="jersey_number" id="jersey_number" value="{{ old('jersey_number') }}" min="0" max="999" {{ $required ? 'required' : '' }}
                                                    class="form-control @error('jersey_number') border-red-500 @enderror" placeholder="e.g. 7">
                                                @error('jersey_number')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('tshirt_size')
                                                @php $tshirtOptions = \App\Helpers\PlayerFormConfig::sizeOptions('tshirt_sizes', \App\Helpers\PlayerFormConfig::defaultTshirtSizes()); @endphp
                                                <label for="tshirt_size" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <select name="tshirt_size" id="tshirt_size" class="form-control" {{ $required ? 'required' : '' }}>
                                                    <option value="">Select size</option>
                                                    @foreach($tshirtOptions as $size)
                                                        <option value="{{ $size }}" {{ old('tshirt_size') === $size ? 'selected' : '' }}>{{ $size }}</option>
                                                    @endforeach
                                                </select>
                                                @error('tshirt_size')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('pant_size')
                                                @php $pantOptions = \App\Helpers\PlayerFormConfig::sizeOptions('pant_sizes', \App\Helpers\PlayerFormConfig::defaultPantSizes()); @endphp
                                                <label for="pant_size" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <select name="pant_size" id="pant_size" class="form-control" {{ $required ? 'required' : '' }}>
                                                    <option value="">Select size</option>
                                                    @foreach($pantOptions as $size)
                                                        <option value="{{ $size }}" {{ old('pant_size') === $size ? 'selected' : '' }}>{{ $size }}</option>
                                                    @endforeach
                                                </select>
                                                @error('pant_size')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('player_type')
                                                @if($playerTypes->count() > 0)
                                                <label for="player_type_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <select name="player_type_id" id="player_type_id" class="form-control">
                                                    <option value="">Select type</option>
                                                    @foreach($playerTypes as $type)
                                                        <option value="{{ $type->id }}" {{ old('player_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name ?? $type->type }}</option>
                                                    @endforeach
                                                </select>
                                                @error('player_type_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @endif
                                                @break

                                            @case('batting_profile')
                                                @if($battingProfiles->count() > 0)
                                                <label for="batting_profile_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <select name="batting_profile_id" id="batting_profile_id" class="form-control">
                                                    <option value="">Select dominant hand</option>
                                                    @foreach($battingProfiles as $profile)
                                                        <option value="{{ $profile->id }}" {{ old('batting_profile_id') == $profile->id ? 'selected' : '' }}>{{ $profile->name ?? $profile->style }}</option>
                                                    @endforeach
                                                </select>
                                                @error('batting_profile_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @endif
                                                @break

                                            @case('batting_mode')
                                                <label for="batting_mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <select name="batting_mode" id="batting_mode" class="form-control">
                                                    <option value="">Select batting mode</option>
                                                    @foreach(['Aggressive Batsman','Defensive Batsman','Finisher','Anchor','Power Hitter'] as $mode)
                                                        <option value="{{ $mode }}" {{ old('batting_mode') === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                                                    @endforeach
                                                </select>
                                                @error('batting_mode')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('preferred_batting_position')
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <p class="text-xs text-gray-500 mb-2">Select up to 2 positions</p>
                                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                    @foreach(['Opener','3','4','5','6','7','8',"I'm Flexible"] as $pos)
                                                        <label class="flex items-center gap-2 p-2 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800">
                                                            <input type="checkbox" name="preferred_batting_positions[]" value="{{ $pos }}"
                                                                x-model="selectedPositions"
                                                                :disabled="!selectedPositions.includes('{{ $pos }}') && selectedPositions.length >= 2"
                                                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $pos }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                                @error('preferred_batting_positions')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @error('preferred_batting_positions.*')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('bowling_profile')
                                                @if($bowlingProfiles->count() > 0)
                                                <label for="bowling_profile_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <select name="bowling_profile_id" id="bowling_profile_id" class="form-control">
                                                    <option value="">Select bowling style</option>
                                                    @foreach($bowlingProfiles as $profile)
                                                        <option value="{{ $profile->id }}" {{ old('bowling_profile_id') == $profile->id ? 'selected' : '' }}>{{ $profile->name ?? $profile->style }}</option>
                                                    @endforeach
                                                </select>
                                                @error('bowling_profile_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @endif
                                                @break

                                            @case('is_wicket_keeper')
                                                <label class="flex items-center gap-2">
                                                    <input type="checkbox" name="is_wicket_keeper" value="1" {{ old('is_wicket_keeper') ? 'checked' : '' }}
                                                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">{!! $label !!}</span>
                                                </label>
                                                @break

                                            @case('total_matches')
                                            @case('total_runs')
                                            @case('total_wickets')
                                                <label for="{{ $key }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <input type="number" name="{{ $key }}" id="{{ $key }}" value="{{ old($key, 0) }}" min="0"
                                                    class="form-control @error($key) border-red-500 @enderror" placeholder="0">
                                                @error($key)<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('transportation')
                                                <label for="transportation_mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <select name="transportation_mode" id="transportation_mode" class="form-control">
                                                    <option value="">Select transportation</option>
                                                    <option value="self" {{ old('transportation_mode') === 'self' ? 'selected' : '' }}>Self Transportation</option>
                                                    <option value="required" {{ old('transportation_mode') === 'required' ? 'selected' : '' }}>Transportation Required</option>
                                                </select>
                                                @error('transportation_mode')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                @break

                                            @case('travel_plan')
                                                <label for="has_travel_plan" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <select name="has_travel_plan" id="has_travel_plan" class="form-control" x-model="hasTravelPlan">
                                                    <option value="">Select</option>
                                                    <option value="no" {{ old('has_travel_plan') === 'no' ? 'selected' : '' }}>No</option>
                                                    <option value="yes" {{ old('has_travel_plan') === 'yes' ? 'selected' : '' }}>Yes</option>
                                                </select>
                                                @error('has_travel_plan')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                <div x-show="hasTravelPlan === 'yes'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                                                    <div>
                                                        <label for="travel_date_from" class="block text-xs text-gray-500 mb-1">Travel From Date</label>
                                                        <input type="date" name="travel_date_from" id="travel_date_from" value="{{ old('travel_date_from') }}" class="form-control">
                                                        @error('travel_date_from')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                    </div>
                                                    <div>
                                                        <label for="travel_date_to" class="block text-xs text-gray-500 mb-1">Travel To Date</label>
                                                        <input type="date" name="travel_date_to" id="travel_date_to" value="{{ old('travel_date_to') }}" class="form-control">
                                                        @error('travel_date_to')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                                    </div>
                                                </div>
                                                @break

                                            @case('image')
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{!! $label !!} {!! $reqMark !!}</label>
                                                <x-player-image-upload name="image_path" :required="$required" :field-config="$fieldConfig" />
                                                @break

                                            @case('terms_and_conditions')
                                                @php
                                                    $tcContent = ($tournamentSettings->terms_and_conditions_content ?? '') ?: '';
                                                @endphp
                                                @if(!empty($tcContent))
                                                <div x-data="{ showTC: false }">
                                                    <button type="button" @click="showTC = !showTC"
                                                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 text-sm underline mb-2">
                                                        View Terms & Conditions
                                                    </button>
                                                    <div x-show="showTC" x-cloak class="mb-3 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg text-sm text-gray-700 dark:text-gray-300 max-h-48 overflow-y-auto whitespace-pre-wrap border border-gray-200 dark:border-gray-700">{{ $tcContent }}</div>
                                                </div>
                                                @endif
                                                <label class="flex items-center gap-2">
                                                    <input type="checkbox" name="terms_and_conditions" value="1"
                                                        {{ old('terms_and_conditions') ? 'checked' : '' }}
                                                        {{ $required ? 'required' : '' }}
                                                        class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</span>
                                                </label>
                                                @error('terms_and_conditions')<p class="text-sm text-red-500 mt-1">{{ $message }}</p>@enderror
                                                @break

                                            @default
                                                {{-- Fallback for any unhandled field --}}
                                                <label for="{{ $key }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{!! $label !!} {!! $reqMark !!}</label>
                                                <input type="text" name="{{ $key }}" id="{{ $key }}" value="{{ old($key) }}" class="form-control">
                                                @error($key)<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                        @endswitch

                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        {{-- Submit Buttons --}}
                        <div class="mt-6 flex justify-end space-x-4">
                            <a href="{{ route('team-manager.dashboard') }}"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Cancel
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Register as Player
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
