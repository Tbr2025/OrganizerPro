@php
    /** @var string $key */
    $cfg = $fieldConfig[$key] ?? ['label' => $key, 'required' => false];
    $label = $cfg['label'] ?? $key;
    $required = $cfg['required'] ?? false;
    $reqMark = $required ? '<span class="reg-req">*</span>' : '';
    $fullWidth = in_array($key, [
        'cricheroes_profile_url', 'employer_address',
        'played_ys_ipl_s1', 'is_wicket_keeper', 'transportation', 'travel_plan',
        'image', 'terms_and_conditions', 'registration_team',
    ], true);

    // Employer fields are only shown for a work visa (Alpine-driven).
    $employerField = in_array($key, ['employer_name', 'employer_position', 'employer_address'], true);
    // Visa validity is only shown for a visit visa.
    $visitVisaField = $key === 'visa_expiry';
@endphp

<div class="{{ $fullWidth ? 'md:col-span-2' : '' }}"
     @if($employerField) x-show="visaStatus === 'work_visa'" x-cloak
     @elseif($visitVisaField) x-show="visaStatus === 'visit_visa'" x-cloak @endif>
@switch($key)

    @case('first_name')
    @case('last_name')
        <label for="{{ $key }}" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="text" name="{{ $key }}" id="{{ $key }}" value="{{ old($key) }}" {{ $required ? 'required' : '' }}
               class="reg-input" placeholder="{{ $label }}">
        @error($key)<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('email')
        <label for="email" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="email" name="email" id="email" value="{{ old('email') }}" {{ $required ? 'required' : '' }}
               class="reg-input" placeholder="your@email.com">
        @error('email')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('date_of_birth')
        <label for="date_of_birth" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="date" name="date_of_birth" id="date_of_birth" value="{{ old('date_of_birth') }}" {{ $required ? 'required' : '' }} class="reg-input">
        @error('date_of_birth')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('mobile_number')
        <label for="mobile_number_full" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="tel" name="mobile_number_full" id="mobile_number_full" value="{{ old('mobile_number_full') }}" {{ $required ? 'required' : '' }}
               class="reg-input" placeholder="971501234567">
        <p class="reg-hint">Include country code, no + sign</p>
        @error('mobile_number_full')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('cricheroes_number')
        <label for="cricheroes_number_full" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="tel" name="cricheroes_number_full" id="cricheroes_number_full" value="{{ old('cricheroes_number_full') }}" class="reg-input" placeholder="971501234567">
        @error('cricheroes_number_full')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('cricheroes_profile_url')
        <label for="cricheroes_profile_url" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="url" name="cricheroes_profile_url" id="cricheroes_profile_url" value="{{ old('cricheroes_profile_url') }}" class="reg-input" placeholder="https://cricheroes.com/player-profile/...">
        @error('cricheroes_profile_url')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('country')
        <label for="country" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="country" id="country" class="reg-select" x-model="selectedCountry" {{ $required ? 'required' : '' }}>
            <option value="">Select your nationality</option>
            @foreach (config('countries.list', []) as $code => $name)
                <option value="{{ $code }}">{{ $name }}</option>
            @endforeach
        </select>
        @error('country')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('state')
        <label for="state" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="state" id="state" class="reg-select" x-model="stateValue" x-show="hasStates" :disabled="!hasStates" {{ $required ? 'required' : '' }}>
            <option value="">Select your state</option>
            <template x-for="s in (statesByCountry[selectedCountry] || [])" :key="s">
                <option :value="s" x-text="s"></option>
            </template>
        </select>
        <input type="text" name="state" class="reg-input" x-model="stateValue" x-show="!hasStates" :disabled="hasStates" placeholder="Enter state / province" {{ $required ? 'required' : '' }}>
        @error('state')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('location')
        @if($locations->count() > 0)
        <label for="location_id" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="location_id" id="location_id" class="reg-select">
            <option value="">Select your location</option>
            @foreach($locations as $location)
                <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
            @endforeach
        </select>
        @error('location_id')<p class="reg-err">{{ $message }}</p>@enderror
        @endif
        @break

    @case('registration_team')
        @if($teams->count() > 0)
        <div>
            <label for="team_id" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
            <select name="team_id" id="team_id" x-model="selectedTeam" class="reg-select">
                <option value="">Select your team</option>
                @foreach($teams as $team)
                    <option value="{{ $team->id }}" {{ old('team_id') == $team->id ? 'selected' : '' }}>{{ $team->name }}</option>
                @endforeach
                <option value="other">Other (specify below)</option>
            </select>
            @error('team_id')<p class="reg-err">{{ $message }}</p>@enderror
        </div>
        <div x-show="selectedTeam === 'other'" x-cloak class="mt-4">
            <label for="team_name_ref" class="reg-label">Team Name</label>
            <input type="text" name="team_name_ref" id="team_name_ref" value="{{ old('team_name_ref') }}" class="reg-input" placeholder="Enter your team name">
            @error('team_name_ref')<p class="reg-err">{{ $message }}</p>@enderror
        </div>
        @endif
        @break

    @case('visa_status')
        <label for="visa_status" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="visa_status" id="visa_status" class="reg-select" x-model="visaStatus" {{ $required ? 'required' : '' }}>
            <option value="">Select visa status</option>
            @foreach(config('registration.visa_statuses', []) as $val => $vlabel)
                <option value="{{ $val }}" {{ old('visa_status') === $val ? 'selected' : '' }}>{{ $vlabel }}</option>
            @endforeach
        </select>
        @error('visa_status')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('visa_expiry')
        {{-- Only relevant for a visit visa --}}
        <label for="visa_expiry" class="reg-label">{!! $label !!} <span class="reg-req" x-show="visaStatus === 'visit_visa'">*</span></label>
        <input type="date" name="visa_expiry" id="visa_expiry" value="{{ old('visa_expiry') }}" class="reg-input">
        @error('visa_expiry')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('employer_name')
    @case('employer_position')
        {{-- Only relevant for a work visa --}}
        <label for="{{ $key }}" class="reg-label">{!! $label !!} <span class="reg-req" x-show="visaStatus === 'work_visa'">*</span></label>
        <input type="text" name="{{ $key }}" id="{{ $key }}" value="{{ old($key) }}" class="reg-input" placeholder="{{ $label }}">
        @error($key)<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('employer_address')
        <label for="employer_address" class="reg-label">{!! $label !!} <span class="reg-req" x-show="visaStatus === 'work_visa'">*</span></label>
        <textarea name="employer_address" id="employer_address" rows="2" class="reg-input" placeholder="Office address">{{ old('employer_address') }}</textarea>
        @error('employer_address')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('available_saturday')
    @case('available_sunday')
        <label class="reg-check">
            <input type="checkbox" name="{{ $key }}" id="{{ $key }}" value="1" {{ old($key) ? 'checked' : '' }} {{ $required ? 'required' : '' }}>
            <span class="text-sm">{!! $label !!} {!! $reqMark !!}</span>
        </label>
        @error($key)<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('played_ys_ipl_s1')
        <label class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <div class="flex gap-3">
            <label class="reg-check" style="flex:1;">
                <input type="radio" name="played_ys_ipl_s1" value="1" {{ old('played_ys_ipl_s1') === '1' ? 'checked' : '' }} style="accent-color:var(--accent);">
                <span class="text-sm">Yes</span>
            </label>
            <label class="reg-check" style="flex:1;">
                <input type="radio" name="played_ys_ipl_s1" value="0" {{ old('played_ys_ipl_s1') === '0' ? 'checked' : '' }} style="accent-color:var(--accent);">
                <span class="text-sm">No</span>
            </label>
        </div>
        @error('played_ys_ipl_s1')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('jersey_name')
        <label for="jersey_name" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="text" name="jersey_name" id="jersey_name" value="{{ old('jersey_name') }}" {{ $required ? 'required' : '' }} class="reg-input" placeholder="Name on jersey">
        @error('jersey_name')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('jersey_number')
        <label for="jersey_number" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="number" name="jersey_number" id="jersey_number" value="{{ old('jersey_number') }}" min="0" max="999" {{ $required ? 'required' : '' }} class="reg-input" placeholder="e.g. 7">
        @error('jersey_number')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('kit_size')
        @if($kitSizes->count() > 0)
        <label for="kit_size_id" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="kit_size_id" id="kit_size_id" class="reg-select">
            <option value="">Select size</option>
            @foreach($kitSizes as $size)
                <option value="{{ $size->id }}" {{ old('kit_size_id') == $size->id ? 'selected' : '' }}>{{ $size->size ?? $size->name }}</option>
            @endforeach
        </select>
        @error('kit_size_id')<p class="reg-err">{{ $message }}</p>@enderror
        @endif
        @break

    @case('player_type')
        @if($playerTypes->count() > 0)
        <label for="player_type_id" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="player_type_id" id="player_type_id" class="reg-select">
            <option value="">Select type</option>
            @foreach($playerTypes as $type)
                <option value="{{ $type->id }}" {{ old('player_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name ?? $type->type }}</option>
            @endforeach
        </select>
        @error('player_type_id')<p class="reg-err">{{ $message }}</p>@enderror
        @endif
        @break

    @case('batting_profile')
        @if($battingProfiles->count() > 0)
        <label for="batting_profile_id" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="batting_profile_id" id="batting_profile_id" class="reg-select">
            <option value="">Select batting style</option>
            @foreach($battingProfiles as $profile)
                <option value="{{ $profile->id }}" {{ old('batting_profile_id') == $profile->id ? 'selected' : '' }}>{{ $profile->name ?? $profile->style }}</option>
            @endforeach
        </select>
        @error('batting_profile_id')<p class="reg-err">{{ $message }}</p>@enderror
        @endif
        @break

    @case('bowling_profile')
        @if($bowlingProfiles->count() > 0)
        <label for="bowling_profile_id" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="bowling_profile_id" id="bowling_profile_id" class="reg-select">
            <option value="">Select bowling style</option>
            @foreach($bowlingProfiles as $profile)
                <option value="{{ $profile->id }}" {{ old('bowling_profile_id') == $profile->id ? 'selected' : '' }}>{{ $profile->name ?? $profile->style }}</option>
            @endforeach
        </select>
        @error('bowling_profile_id')<p class="reg-err">{{ $message }}</p>@enderror
        @endif
        @break

    @case('is_wicket_keeper')
        <label class="reg-check">
            <input type="checkbox" name="is_wicket_keeper" id="is_wicket_keeper" value="1" {{ old('is_wicket_keeper') ? 'checked' : '' }}>
            <span class="text-sm"><i class="fas fa-mitten text-accent mr-2"></i>{!! $label !!}</span>
        </label>
        @break

    @case('total_matches')
    @case('total_runs')
    @case('total_wickets')
        <label for="{{ $key }}" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="number" name="{{ $key }}" id="{{ $key }}" value="{{ old($key, 0) }}" min="0" class="reg-input" placeholder="0">
        @error($key)<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('transportation')
        <label class="reg-check">
            <input type="checkbox" name="transportation_required" id="transportation_required" value="1" {{ old('transportation_required') ? 'checked' : '' }}>
            <span class="text-sm">{!! $label !!}</span>
        </label>
        @break

    @case('travel_plan')
        <label class="reg-check">
            <input type="checkbox" name="no_travel_plan" id="no_travel_plan" value="1" x-model="noTravel"
                   @change="if ($el.checked) { document.getElementById('travel_date_from').value=''; document.getElementById('travel_date_to').value=''; }"
                   {{ old('no_travel_plan') ? 'checked' : '' }}>
            <span class="text-sm">{!! $label !!}</span>
        </label>
        <div x-show="!noTravel" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-3">
            <div>
                <label for="travel_date_from" class="reg-label">Travel From Date</label>
                <input type="date" name="travel_date_from" id="travel_date_from" value="{{ old('travel_date_from') }}" class="reg-input">
                @error('travel_date_from')<p class="reg-err">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="travel_date_to" class="reg-label">Travel To Date</label>
                <input type="date" name="travel_date_to" id="travel_date_to" value="{{ old('travel_date_to') }}" class="reg-input">
                @error('travel_date_to')<p class="reg-err">{{ $message }}</p>@enderror
            </div>
        </div>
        @break

    @case('image')
        @include('public.registration.partials.player-image-upload', ['fieldConfig' => $fieldConfig, 'embedded' => true, 'fieldLabel' => $label])
        @break

    @case('terms_and_conditions')
        @php $hasTC = !empty($settings->terms_and_conditions_content ?? ''); @endphp
        <div x-data="{
                showTC: false,
                accepted: {{ old('terms_and_conditions') ? 'true' : 'false' }},
                readToEnd: false,
                openTC() {
                    this.showTC = true; this.readToEnd = false;
                    this.$nextTick(() => { const b = this.$refs.tcBody; if (b && b.scrollHeight <= b.clientHeight + 4) this.readToEnd = true; });
                },
                onScroll(el) { if (el.scrollTop + el.clientHeight >= el.scrollHeight - 8) this.readToEnd = true; }
             }">
            {{-- Clicking the checkbox opens the T&C popup; accepting inside ticks it. --}}
            <label class="reg-check" @if($hasTC) @click.prevent="openTC()" @endif>
                <input type="checkbox" name="terms_and_conditions" id="terms_and_conditions" value="1"
                       x-model="accepted" {{ $required ? 'required' : '' }}
                       @if($hasTC) tabindex="-1" style="pointer-events:none" @endif>
                <span class="text-sm">{!! $label !!} {!! $reqMark !!}</span>
            </label>
            @error('terms_and_conditions')<p class="reg-err">{{ $message }}</p>@enderror

            @if($hasTC)
                {{-- Popup: scroll to the end to enable Accept; Accept ticks the box, Close leaves it unchecked --}}
                <template x-teleport="body">
                    <div x-show="showTC" x-cloak class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
                         style="background:rgba(0,0,0,0.7);" @keydown.escape.window="showTC = false">
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden"
                             style="max-height:85vh;" @click.outside="showTC = false">
                            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Terms &amp; Conditions</h3>
                                <button type="button" @click="showTC = false" class="text-gray-400 hover:text-gray-700 dark:hover:text-white text-xl leading-none">&times;</button>
                            </div>
                            <div x-ref="tcBody" @scroll="onScroll($el)"
                                 class="flex-1 min-h-0 overflow-y-auto p-5 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $settings->terms_and_conditions_content }}</div>
                            <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between gap-2 flex-shrink-0">
                                <span class="text-xs text-gray-400" x-show="!readToEnd">Scroll to the end to accept.</span>
                                <span class="flex-1"></span>
                                <button type="button" @click="accepted = false; showTC = false"
                                        class="px-4 py-2 rounded-lg text-sm bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300 font-medium">Reject</button>
                                <button type="button" :disabled="!readToEnd" @click="accepted = true; showTC = false"
                                        :class="readToEnd ? 'hover:bg-emerald-700' : 'opacity-50 cursor-not-allowed'"
                                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-emerald-600">Accept</button>
                            </div>
                        </div>
                    </div>
                </template>
            @endif
        </div>
        @break

@endswitch
</div>
