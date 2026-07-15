@extends('backend.layouts.app')

@section('title')
    Edit Player | {{ config('app.name') }}
@endsection

@section('admin-content')
    <div class="p-4 mx-auto md:p-6">
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

                        @php $canVerify = auth()->user()->hasAnyRole(['Superadmin', 'Admin']); @endphp

                        {{-- ═══════════════════════════════════════════════════════ --}}
                        {{-- Section 1: Basic Information                           --}}
                        {{-- ═══════════════════════════════════════════════════════ --}}
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Basic Information</h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mb-8">

                            {{-- First Name --}}
                            <div class="space-y-1">
                                <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" id="first_name" value="{{ old('first_name', $player->first_name) }}" required
                                    class="form-control @error('first_name') border-red-500 @enderror">
                                @error('first_name')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

                            {{-- Last Name --}}
                            <div class="space-y-1">
                                <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" id="last_name" value="{{ old('last_name', $player->last_name) }}" required
                                    class="form-control @error('last_name') border-red-500 @enderror">
                                @error('last_name')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

                            {{-- Email --}}
                            <div class="space-y-1">
                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Email @if($fieldConfig['email']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input type="text" id="email" name="email"
                                        value="{{ old('email', $player->email) }}"
                                        class="form-control @error('email') border-red-500 @enderror">
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_email" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_email', $player['verified_email'] ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('email')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

                            {{-- Date of Birth --}}
                            <div class="space-y-1">
                                <label for="date_of_birth" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date of Birth</label>
                                <input type="date" name="date_of_birth" id="date_of_birth" value="{{ old('date_of_birth', optional($player->date_of_birth)->format('Y-m-d')) }}"
                                    class="form-control @error('date_of_birth') border-red-500 @enderror">
                                @error('date_of_birth')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

                            {{-- Nationality + State (cascading) --}}
                            <div style="display: contents" x-data="{
                                selectedCountry: @js(old('country', $player->country ?? '')),
                                stateValue: @js(old('state', $player->state ?? '')),
                                statesByCountry: @js(config('registration.states_by_country')),
                                get hasStates() { return Array.isArray(this.statesByCountry[this.selectedCountry]) && this.statesByCountry[this.selectedCountry].length > 0; },
                            }">
                                {{-- Nationality --}}
                                <div class="space-y-1">
                                    <label for="country" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ __('Nationality') }} @if($fieldConfig['country']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                    </label>
                                    <div class="flex items-center space-x-2">
                                        <select name="country" id="country" x-model="selectedCountry" class="form-control @error('country') border-red-500 @enderror">
                                            <option value="">-- Select Nationality --</option>
                                            @foreach (config('countries.list', []) as $code => $name)
                                                <option value="{{ $code }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                        <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                            <input type="checkbox" name="verified_country" value="1"
                                                class="sr-only peer"
                                                {{ old('verified_country', $player->verified_country ?? false) ? 'checked' : '' }}
                                                @unless ($canVerify) disabled @endunless>
                                            <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                            <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                            <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                        </label>
                                    </div>
                                    @error('country')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                </div>

                                {{-- State / Province --}}
                                <div class="space-y-1">
                                    <label for="state" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('State / Province') }}</label>
                                    <select name="state" id="state" class="form-control @error('state') border-red-500 @enderror"
                                            x-model="stateValue" x-show="hasStates" :disabled="!hasStates">
                                        <option value="">-- Select State --</option>
                                        <template x-for="s in (statesByCountry[selectedCountry] || [])" :key="s">
                                            <option :value="s" x-text="s"></option>
                                        </template>
                                    </select>
                                    <input type="text" name="state" class="form-control @error('state') border-red-500 @enderror"
                                           x-model="stateValue" x-show="!hasStates" :disabled="hasStates" placeholder="Enter state / province">
                                    @error('state')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                </div>
                            </div>{{-- /cascading --}}

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
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_mobile_number_full" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_mobile_number_full', $player->verified_mobile_number_full ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('mobile_number_full')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
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
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_cricheroes_number_full" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_cricheroes_number_full', $player->verified_cricheroes_number_full ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('cricheroes_number_full')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
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
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_cricheroes_profile_url" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_cricheroes_profile_url', $player->verified_cricheroes_profile_url ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('cricheroes_profile_url')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

                            {{-- Player Location --}}
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
                                @error('location_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

                            {{-- Registration Team --}}
                            <div class="space-y-1">
                                <label for="team_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Registration Team
                                    <span class="text-xs text-gray-500">(Original team)</span>
                                    @if($fieldConfig['registration_team']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                <div class="flex items-center space-x-2">
                                    <select name="team_id" id="team_id" class="form-control">
                                        <option value="">-- Select Registration Team --</option>
                                        @foreach ($teams as $option)
                                            <option value="{{ $option->id }}"
                                                {{ old('team_id', $player->team_id) == $option->id ? 'selected' : '' }}>
                                                {{ $option->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="team_name_ref" id="team_name_ref"
                                        placeholder="Enter Team Name (if Others)"
                                        value="{{ old('team_name_ref', $player->team_name_ref ?? '') }}"
                                        class="form-control w-48">
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_team_id" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_team_id', $player['verified_team_id'] ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('team_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

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
                                @error('actual_team_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        {{-- ═══════════════════════════════════════════════════════ --}}
                        {{-- Section 2: Visa & Employment                          --}}
                        {{-- ═══════════════════════════════════════════════════════ --}}
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Visa & Employment</h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mb-8" x-data="{ visaStatus: @js(old('visa_status', $player->visa_status ?? '')) }">

                            {{-- Visa Status --}}
                            <div class="space-y-1">
                                <label for="visa_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Visa Status') }}</label>
                                <select name="visa_status" id="visa_status" x-model="visaStatus" class="form-control">
                                    <option value="">-- Select --</option>
                                    @foreach(config('registration.visa_statuses', []) as $val => $label)
                                        <option value="{{ $val }}" {{ old('visa_status', $player->visa_status) === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Visa Validity (visit visa only) --}}
                            <div class="space-y-1" x-show="visaStatus === 'visit_visa'" x-cloak>
                                <label for="visa_expiry" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Visa Validity (Expiry Date)') }} <span class="text-red-500">*</span></label>
                                <input type="date" name="visa_expiry" id="visa_expiry" value="{{ old('visa_expiry', optional($player->visa_expiry)->format('Y-m-d')) }}" class="form-control">
                                @error('visa_expiry')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>

                            {{-- Employer Name --}}
                            <div class="space-y-1" x-show="visaStatus === 'work_visa'" x-cloak>
                                <label for="employer_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Employer Name') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="employer_name" id="employer_name" value="{{ old('employer_name', $player->employer_name) }}" class="form-control">
                                @error('employer_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>

                            {{-- Position --}}
                            <div class="space-y-1" x-show="visaStatus === 'work_visa'" x-cloak>
                                <label for="employer_position" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Position') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="employer_position" id="employer_position" value="{{ old('employer_position', $player->employer_position) }}" class="form-control">
                                @error('employer_position')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>

                            {{-- Employer Address --}}
                            <div class="space-y-1 sm:col-span-2" x-show="visaStatus === 'work_visa'" x-cloak>
                                <label for="employer_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Employer Address') }} <span class="text-red-500">*</span></label>
                                <textarea name="employer_address" id="employer_address" rows="2" class="form-control">{{ old('employer_address', $player->employer_address) }}</textarea>
                                @error('employer_address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        {{-- ═══════════════════════════════════════════════════════ --}}
                        {{-- Section 3: Availability                               --}}
                        {{-- ═══════════════════════════════════════════════════════ --}}
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Availability</h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mb-8">

                            <div class="space-y-1">
                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="available_saturday" value="1" {{ old('available_saturday', $player->available_saturday) ? 'checked' : '' }}>
                                    {{ __('Available to play on Saturdays') }}
                                </label>
                            </div>

                            <div class="space-y-1">
                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="available_sunday" value="1" {{ old('available_sunday', $player->available_sunday) ? 'checked' : '' }}>
                                    {{ __('Available to play on Sundays') }}
                                </label>
                            </div>

                            {{-- Played YS IPL Season 1 --}}
                            <div class="space-y-1">
                                <label for="played_ys_ipl_s1" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Played YS IPL Season 1?') }}</label>
                                <select name="played_ys_ipl_s1" id="played_ys_ipl_s1" class="form-control">
                                    <option value="0" {{ old('played_ys_ipl_s1', $player->played_ys_ipl_s1 ? '1':'0') === '0' ? 'selected' : '' }}>No</option>
                                    <option value="1" {{ old('played_ys_ipl_s1', $player->played_ys_ipl_s1 ? '1':'0') === '1' ? 'selected' : '' }}>Yes</option>
                                </select>
                            </div>
                        </div>

                        {{-- ═══════════════════════════════════════════════════════ --}}
                        {{-- Section 4: Jersey Information                         --}}
                        {{-- ═══════════════════════════════════════════════════════ --}}
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Jersey Information</h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mb-8">

                            {{-- Jersey Name --}}
                            <div class="space-y-1">
                                <label for="jersey_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Jersey Name @if($fieldConfig['jersey_name']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input type="text" id="jersey_name" name="jersey_name"
                                        value="{{ old('jersey_name', $player->jersey_name) }}"
                                        class="form-control @error('jersey_name') border-red-500 @enderror">
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_jersey_name" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_jersey_name', $player['verified_jersey_name'] ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('jersey_name')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

                            {{-- Jersey Number --}}
                            <div class="space-y-1">
                                <label for="jersey_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Jersey Number @if($fieldConfig['jersey_number']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                <div class="flex items-center space-x-2">
                                    <input type="text" id="jersey_number" name="jersey_number"
                                        value="{{ old('jersey_number', $player->jersey_number) }}"
                                        class="form-control @error('jersey_number') border-red-500 @enderror">
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_jersey_number" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_jersey_number', $player['verified_jersey_number'] ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('jersey_number')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

                            {{-- T-Shirt Size --}}
                            @if($fieldConfig['tshirt_size']['visible'] ?? true)
                            <div class="space-y-1">
                                <label for="tshirt_size" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('T-Shirt Size') }} @if($fieldConfig['tshirt_size']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                @php $tshirtOptions = \App\Helpers\PlayerFormConfig::sizeOptions('tshirt_sizes', \App\Helpers\PlayerFormConfig::defaultTshirtSizes()); @endphp
                                <select name="tshirt_size" id="tshirt_size"
                                    {{ ($fieldConfig['tshirt_size']['required'] ?? false) ? 'required' : '' }}
                                    class="form-control @error('tshirt_size') border-red-500 @enderror">
                                    <option value="">-- Select T-Shirt Size --</option>
                                    @foreach ($tshirtOptions as $size)
                                        <option value="{{ $size }}" {{ old('tshirt_size', $player->tshirt_size) === $size ? 'selected' : '' }}>{{ $size }}</option>
                                    @endforeach
                                </select>
                                @error('tshirt_size')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>
                            @endif

                            {{-- Pant Size --}}
                            @if($fieldConfig['pant_size']['visible'] ?? true)
                            <div class="space-y-1">
                                <label for="pant_size" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('Pant Size') }} @if($fieldConfig['pant_size']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                @php $pantOptions = \App\Helpers\PlayerFormConfig::sizeOptions('pant_sizes', \App\Helpers\PlayerFormConfig::defaultPantSizes()); @endphp
                                <select name="pant_size" id="pant_size"
                                    {{ ($fieldConfig['pant_size']['required'] ?? false) ? 'required' : '' }}
                                    class="form-control @error('pant_size') border-red-500 @enderror">
                                    <option value="">-- Select Pant Size --</option>
                                    @foreach ($pantOptions as $size)
                                        <option value="{{ $size }}" {{ old('pant_size', $player->pant_size) === $size ? 'selected' : '' }}>{{ $size }}</option>
                                    @endforeach
                                </select>
                                @error('pant_size')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>
                            @endif

                            {{-- Kit Size (Jersey Size) --}}
                            <div class="space-y-1">
                                <label for="kit_size_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Jersey Size @if($fieldConfig['kit_size']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                <div class="flex items-center space-x-2">
                                    <select name="kit_size_id" id="kit_size_id" class="form-control">
                                        <option value="">-- Select Jersey Size --</option>
                                        @foreach ($kitSizes as $option)
                                            <option value="{{ $option->id }}"
                                                {{ old('kit_size_id', $player->kit_size_id) == $option->id ? 'selected' : '' }}>
                                                {{ $option->size }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_kit_size_id" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_kit_size_id', $player['verified_kit_size_id'] ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('kit_size_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        {{-- ═══════════════════════════════════════════════════════ --}}
                        {{-- Section 5: Player Profile                             --}}
                        {{-- ═══════════════════════════════════════════════════════ --}}
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Player Profile</h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mb-8">

                            {{-- Player Type --}}
                            <div class="space-y-1">
                                <label for="player_type_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Player Type @if($fieldConfig['player_type']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                <div class="flex items-center space-x-2">
                                    <select name="player_type_id" id="player_type_id" class="form-control">
                                        <option value="">-- Select Player Type --</option>
                                        @foreach ($playerTypes as $option)
                                            <option value="{{ $option->id }}"
                                                {{ old('player_type_id', $player->player_type_id) == $option->id ? 'selected' : '' }}>
                                                {{ $option->type }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_player_type_id" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_player_type_id', $player['verified_player_type_id'] ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('player_type_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

                            {{-- Batting Profile --}}
                            <div class="space-y-1">
                                <label for="batting_profile_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Batting Profile @if($fieldConfig['batting_profile']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                <div class="flex items-center space-x-2">
                                    <select name="batting_profile_id" id="batting_profile_id" class="form-control">
                                        <option value="">-- Select Batting Profile --</option>
                                        @foreach ($battingProfiles as $option)
                                            <option value="{{ $option->id }}"
                                                {{ old('batting_profile_id', $player->batting_profile_id) == $option->id ? 'selected' : '' }}>
                                                {{ $option->style }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_batting_profile_id" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_batting_profile_id', $player['verified_batting_profile_id'] ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('batting_profile_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

                            {{-- Batting Mode --}}
                            <div class="space-y-1">
                                <label for="batting_mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Batting Mode</label>
                                <select name="batting_mode" id="batting_mode" class="form-control @error('batting_mode') border-red-500 @enderror">
                                    <option value="">-- Select Batting Mode --</option>
                                    @foreach(['Aggressive Batsman','Defensive Batsman','Finisher','Anchor','Power Hitter'] as $mode)
                                        <option value="{{ $mode }}" {{ old('batting_mode', $player->batting_mode) === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                                    @endforeach
                                </select>
                                @error('batting_mode')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

                            {{-- Bowling Profile --}}
                            <div class="space-y-1">
                                <label for="bowling_profile_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Bowling Profile @if($fieldConfig['bowling_profile']['required'] ?? false)<span class="text-red-500">*</span>@endif
                                </label>
                                <div class="flex items-center space-x-2">
                                    <select name="bowling_profile_id" id="bowling_profile_id" class="form-control">
                                        <option value="">-- Select Bowling Profile --</option>
                                        @foreach ($bowlingProfiles as $option)
                                            <option value="{{ $option->id }}"
                                                {{ old('bowling_profile_id', $player->bowling_profile_id) == $option->id ? 'selected' : '' }}>
                                                {{ $option->style }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_bowling_profile_id" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_bowling_profile_id', $player['verified_bowling_profile_id'] ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </div>
                                @error('bowling_profile_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

                            {{-- Is Wicket Keeper --}}
                            <div class="space-y-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="is_wicket_keeper" value="1"
                                        {{ old('is_wicket_keeper', $player->is_wicket_keeper) ? 'checked' : '' }} class="mr-2">
                                    Is Wicket Keeper?
                                    <label class="relative inline-flex items-center ml-3 {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_is_wicket_keeper" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_is_wicket_keeper', $player['verified_is_wicket_keeper'] ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </label>
                            </div>

                            {{-- Preferred Batting Positions --}}
                            <div class="space-y-1 sm:col-span-2" x-data="{ selectedPositions: @js(old('preferred_batting_positions', $player->preferred_batting_positions ?? [])) }">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Preferred Batting Position <span class="text-xs text-gray-500">(Select up to 3)</span>
                                </label>
                                <div class="grid grid-cols-4 sm:grid-cols-8 gap-2">
                                    @foreach(['Opener','3','4','5','6','7','8',"I'm Flexible"] as $pos)
                                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                                            :class="selectedPositions.includes('{{ $pos }}') ? 'bg-blue-50 border-blue-400 dark:bg-blue-900/30 dark:border-blue-500' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'">
                                            <input type="checkbox" name="preferred_batting_positions[]" value="{{ $pos }}"
                                                x-model="selectedPositions"
                                                :disabled="!selectedPositions.includes('{{ $pos }}') && selectedPositions.length >= 3"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $pos }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('preferred_batting_positions')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        {{-- ═══════════════════════════════════════════════════════ --}}
                        {{-- Section 6: Leather Ball Experience                    --}}
                        {{-- ═══════════════════════════════════════════════════════ --}}
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Leather Ball Experience</h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3 mb-8">
                            @php
                                $stats = [
                                    'total_matches' => 'Total Matches',
                                    'total_runs' => 'Total Runs',
                                    'total_wickets' => 'Total Wickets',
                                ];
                            @endphp

                            @foreach ($stats as $field => $label)
                                <div class="space-y-1">
                                    <label for="{{ $field }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
                                    <input type="number" name="{{ $field }}" id="{{ $field }}"
                                        value="{{ old($field, $player->$field) }}"
                                        class="form-control @error($field) border-red-500 @enderror" min="0">
                                    @error($field)<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>

                        {{-- ═══════════════════════════════════════════════════════ --}}
                        {{-- Section 7: Travel & Transportation                    --}}
                        {{-- ═══════════════════════════════════════════════════════ --}}
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Travel & Transportation</h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mb-8">

                            {{-- Transportation Required --}}
                            <div class="space-y-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="transportation_required" value="1"
                                        {{ old('transportation_required', $player->transportation_required) ? 'checked' : '' }} class="mr-2">
                                    Transportation Required?
                                    <label class="relative inline-flex items-center ml-3 {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_transportation_required" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_transportation_required', $player['verified_transportation_required'] ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>
                                </label>
                            </div>

                            {{-- No Travel Plan --}}
                            <div class="space-y-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="no_travel_plan" value="1"
                                        {{ old('no_travel_plan', $player->no_travel_plan) ? 'checked' : '' }} class="mr-2">
                                    No Travel Plan
                                    <label class="relative inline-flex items-center ml-3 {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                        <input type="checkbox" name="verified_no_travel_plan" value="1"
                                            class="sr-only peer"
                                            {{ old('verified_no_travel_plan', $player['verified_no_travel_plan'] ?? false) ? 'checked' : '' }}
                                            @unless ($canVerify) disabled @endunless>
                                        <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                        <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                    </label>

                                    {{-- Travel date pickers --}}
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
                                </label>
                            </div>
                        </div>

                        {{-- ═══════════════════════════════════════════════════════ --}}
                        {{-- Section 8: Player Photo                               --}}
                        {{-- ═══════════════════════════════════════════════════════ --}}
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Player Photo</h3>
                        <div class="mb-8">
                            <div class="flex items-center gap-1 mb-2">
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

                            <x-player-image-upload name="image_path" :existing-image="$player->image_path" />

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
                                <label class="relative inline-flex items-center {{ !$canVerify ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                                    <input type="checkbox" name="verified_image_path" value="1"
                                        class="sr-only peer"
                                        {{ old('verified_image_path', $player->verified_image_path ?? false) ? 'checked' : '' }}
                                        @unless ($canVerify) disabled @endunless>
                                    <div class="w-11 h-6 bg-gray-300 rounded-full peer-focus:ring-2 peer-focus:ring-indigo-400 dark:bg-gray-600 peer-checked:bg-green-500 transition-all duration-300"></div>
                                    <div class="absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full transition-transform duration-300 peer-checked:translate-x-full"></div>
                                    <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">Verified</span>
                                </label>
                            </div>
                        </div>

                        {{-- ═══════════════════════════════════════════════════════ --}}
                        {{-- Section 9: Player Mode & Team (admin-only)            --}}
                        {{-- ═══════════════════════════════════════════════════════ --}}
                        @if ($player->status === 'approved')
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">Player Mode & Team</h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 mb-8">

                            {{-- Player Mode --}}
                            <div class="space-y-1">
                                <label for="player_mode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Player Mode</label>
                                <select name="player_mode" id="player_mode"
                                    class="form-control @error('player_mode') border-red-500 @enderror">
                                    <option value="normal" {{ old('player_mode', $player->player_mode) !== 'retained' ? 'selected' : '' }}>Normal</option>
                                    <option value="retained" {{ old('player_mode', $player->player_mode) === 'retained' ? 'selected' : '' }}>Retained</option>
                                </select>
                                @error('player_mode')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>

                            {{-- Retained Value --}}
                            <div class="space-y-1">
                                <label for="retained_value" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Retained Value</label>
                                <input type="number" name="retained_value" id="retained_value"
                                    value="{{ old('retained_value', $player->retained_value) }}"
                                    min="0" step="any" placeholder="e.g. 500000"
                                    class="form-control @error('retained_value') border-red-500 @enderror">
                                @error('retained_value')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        @endif

                        <input type="hidden" name="intimate" id="intimate" value="0">

                        {{-- Submit Buttons --}}
                        <div class="mt-6">
                            <x-buttons.submit-buttons cancelUrl="{{ route('admin.players.index') }}" />
                        </div>
                        <div class="mt-5 mb-5">
                            <button type="submit" onclick="document.getElementById('intimate').value = 1;"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Intimate Player
                            </button>

                            @if ($hasWelcomeTemplate)
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
                                    @if ($welcomeRegistration && $welcomeRegistration->tournament)
                                        No <strong>welcome_card</strong> template for {{ $welcomeRegistration->tournament->name }}.
                                        <a href="{{ route('admin.tournaments.templates.create', $welcomeRegistration->tournament) }}?type=welcome_card"
                                            class="underline text-blue-600 hover:text-blue-800">Create one now.</a>
                                    @else
                                        This player isn't linked to a tournament yet, so a welcome card can't be generated.
                                    @endif
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
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-blue-800 text-sm space-y-2 col-span-2">
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
                                    selected template and sends it via email. Need to verify all the details to send
                                    welcome message.
                                </p>
                            </div>
                            <div class="flex items-start">
                                <span class="material-icons text-blue-400 mr-2">info</span>
                                <p>
                                    <strong>Active Player</strong> So that player can edit their information from their
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
