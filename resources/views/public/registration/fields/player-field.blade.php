@php
    /** @var string $key */
    $prefill = $prefill ?? [];
    $cfg = $fieldConfig[$key] ?? ['label' => $key, 'required' => false];
    $label = $cfg['label'] ?? $key;
    $required = $cfg['required'] ?? false;
    $reqMark = $required ? '<span class="reg-req">*</span>' : '';
    $fullWidth = in_array($key, [
        'cricheroes_profile_url', 'employer_address',
        'played_ys_ipl_s1', 'is_wicket_keeper', 'transportation', 'travel_plan',
        'image', 'terms_and_conditions', 'registration_team', 'playing_team', 'preferred_batting_position',
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
        @php $isPrefilled = !empty($prefill[$key]); @endphp
        <label for="{{ $key }}" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        @if($isPrefilled)
            <input type="text" id="{{ $key }}" value="{{ $prefill[$key] }}" disabled class="reg-input bg-gray-100 dark:bg-gray-700 cursor-not-allowed opacity-75">
            <input type="hidden" name="{{ $key }}" value="{{ $prefill[$key] }}">
        @else
            <input type="text" name="{{ $key }}" id="{{ $key }}" value="{{ old($key) }}" {{ $required ? 'required' : '' }}
                   class="reg-input" placeholder="{{ $label }}">
        @endif
        @error($key)<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('email')
        @php $isPrefilled = !empty($prefill['email']); @endphp
        <label for="email" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        @if($isPrefilled)
            <input type="email" id="email" value="{{ $prefill['email'] }}" disabled class="reg-input bg-gray-100 dark:bg-gray-700 cursor-not-allowed opacity-75">
            <input type="hidden" name="email" value="{{ $prefill['email'] }}">
        @else
            <input type="email" name="email" id="email" value="{{ old('email') }}" {{ $required ? 'required' : '' }}
                   class="reg-input" placeholder="your@email.com">
        @endif
        @error('email')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('date_of_birth')
        @php
            // Age limits (configurable per tournament). min_age → latest allowed DOB
            // (also blocks future dates); max_age → earliest allowed DOB.
            $minAge = $settings->min_age ?? null;
            $maxAge = $settings->max_age ?? null;
            $dobMax = $minAge ? now()->subYears((int) $minAge)->toDateString() : now()->toDateString();
            $dobMin = $maxAge ? now()->subYears((int) $maxAge)->toDateString() : null;
        @endphp
        <label for="date_of_birth" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="date" name="date_of_birth" id="date_of_birth" value="{{ old('date_of_birth') }}"
               max="{{ $dobMax }}" @if($dobMin) min="{{ $dobMin }}" @endif
               {{ $required ? 'required' : '' }} class="reg-input">
        @if($minAge || $maxAge)
            <p class="reg-hint">
                @if($minAge && $maxAge) Age must be between {{ $minAge }} and {{ $maxAge }} years.
                @elseif($minAge) Must be at least {{ $minAge }} years old.
                @else Must be at most {{ $maxAge }} years old. @endif
            </p>
        @endif
        @error('date_of_birth')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('mobile_number')
        <label class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <div class="flex gap-2">
            <select name="mobile_country_code" class="reg-select w-28" style="flex:0 0 7rem;" x-model="dialCode">
                @foreach(config('countries.dial_codes', []) as $code => $dial)
                    <option value="{{ $dial }}" {{ old('mobile_country_code', '') === $dial ? 'selected' : '' }}>{{ $dial }} ({{ $code }})</option>
                @endforeach
            </select>
            <input type="tel" name="mobile_national_number" x-ref="mobileNat" class="reg-input flex-1"
                   placeholder="501234567" value="{{ old('mobile_national_number') }}" {{ $required ? 'required' : '' }}>
        </div>
        @error('mobile_country_code')<p class="reg-err">{{ $message }}</p>@enderror
        @error('mobile_national_number')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('cricheroes_number')
        <label class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <div class="flex gap-2">
            <select name="cricheroes_country_code" class="reg-select w-28" style="flex:0 0 7rem;" x-model="cricDialCode">
                @foreach(config('countries.dial_codes', []) as $code => $dial)
                    <option value="{{ $dial }}" {{ old('cricheroes_country_code', '') === $dial ? 'selected' : '' }}>{{ $dial }} ({{ $code }})</option>
                @endforeach
            </select>
            <input type="tel" name="cricheroes_national_number" class="reg-input flex-1"
                   placeholder="501234567" value="{{ old('cricheroes_national_number') }}" {{ $required ? 'required' : '' }}>
        </div>
        @error('cricheroes_country_code')<p class="reg-err">{{ $message }}</p>@enderror
        @error('cricheroes_national_number')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('cricheroes_profile_url')
        <label for="cricheroes_profile_url" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="url" name="cricheroes_profile_url" id="cricheroes_profile_url" value="{{ old('cricheroes_profile_url') }}" {{ $required ? 'required' : '' }} class="reg-input" placeholder="https://cricheroes.com/player-profile/...">
        @error('cricheroes_profile_url')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('country')
        <label for="country" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="country" id="country" class="reg-select" x-model="selectedCountry" autocomplete="off" {{ $required ? 'required' : '' }}>
            <option value="">Select your nationality</option>
            @foreach (config('countries.list', []) as $code => $name)
                <option value="{{ $code }}">{{ $name }}</option>
            @endforeach
        </select>
        @error('country')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('state')
        <label for="state" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="state" id="state" class="reg-select" x-model="stateValue" x-show="hasStates" :disabled="!hasStates" autocomplete="off" {{ $required ? 'required' : '' }}>
            <option value="">Select your state</option>
            <template x-for="s in (statesByCountry[selectedCountry] || [])" :key="s">
                <option :value="s" x-text="s"></option>
            </template>
        </select>
        <input type="text" name="state" class="reg-input" x-model="stateValue" x-show="!hasStates" :disabled="hasStates" autocomplete="off" placeholder="Enter state / province" {{ $required ? 'required' : '' }}>
        @error('state')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('location')
        @if($locations->count() > 0)
        <label for="location_id" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="location_id" id="location_id" class="reg-select" {{ $required ? 'required' : '' }}>
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
            <select name="team_id" id="team_id" x-model="selectedTeam" class="reg-select" {{ $required ? 'required' : '' }}>
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
        @else
        {{-- No pre-defined teams yet: let the applicant type their team name --}}
        <div>
            <label for="team_name_ref" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
            <input type="text" name="team_name_ref" id="team_name_ref" value="{{ old('team_name_ref') }}" class="reg-input" placeholder="Enter your team name" {{ $required ? 'required' : '' }}>
            @error('team_name_ref')<p class="reg-err">{{ $message }}</p>@enderror
        </div>
        @endif
        @break

    @case('playing_team')
        @if(($actualTeams ?? collect())->count() > 0)
        <div>
            <label for="actual_team_id" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
            <select name="actual_team_id" id="actual_team_id" x-model="selectedPlayingTeam" class="reg-select" {{ $required ? 'required' : '' }}>
                <option value="">Select playing team</option>
                @foreach($actualTeams as $team)
                    <option value="{{ $team->id }}" {{ old('actual_team_id') == $team->id ? 'selected' : '' }}>{{ $team->name }}</option>
                @endforeach
                <option value="other">Others</option>
            </select>
            @error('actual_team_id')<p class="reg-err">{{ $message }}</p>@enderror
        </div>
        <div x-show="selectedPlayingTeam === 'other'" x-cloak class="mt-3">
            <label class="reg-label">Team Name <span class="reg-req">*</span></label>
            <input type="text" name="playing_team_name_ref" class="reg-input" placeholder="Enter team name"
                   value="{{ old('playing_team_name_ref') }}"
                   x-bind:required="selectedPlayingTeam === 'other'">
            @error('playing_team_name_ref')<p class="reg-err">{{ $message }}</p>@enderror
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
        {{-- Only relevant for a visit visa; required only if admin marked it required --}}
        <label for="visa_expiry" class="reg-label">{!! $label !!} @if($required)<span class="reg-req" x-show="visaStatus === 'visit_visa'">*</span>@endif</label>
        <input type="date" name="visa_expiry" id="visa_expiry" value="{{ old('visa_expiry') }}" class="reg-input"
               @if($required) x-bind:required="visaStatus === 'visit_visa'" @endif>
        @error('visa_expiry')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('employer_name')
    @case('employer_position')
        {{-- Only relevant for a work visa; required only if admin marked it required --}}
        <label for="{{ $key }}" class="reg-label">{!! $label !!} @if($required)<span class="reg-req" x-show="visaStatus === 'work_visa'">*</span>@endif</label>
        <input type="text" name="{{ $key }}" id="{{ $key }}" value="{{ old($key) }}" class="reg-input" placeholder="{{ $label }}"
               @if($required) x-bind:required="visaStatus === 'work_visa'" @endif>
        @error($key)<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('employer_address')
        <label for="employer_address" class="reg-label">{!! $label !!} @if($required)<span class="reg-req" x-show="visaStatus === 'work_visa'">*</span>@endif</label>
        <textarea name="employer_address" id="employer_address" rows="2" class="reg-input" placeholder="Office address"
                  @if($required) x-bind:required="visaStatus === 'work_visa'" @endif>{{ old('employer_address') }}</textarea>
        @error('employer_address')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('available_saturday')
    @case('available_sunday')
        <label class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <div class="flex gap-3">
            <label class="reg-check" style="flex:1;">
                <input type="radio" name="{{ $key }}" value="1" {{ (string) old($key) === '1' ? 'checked' : '' }} {{ $required ? 'required' : '' }} style="accent-color:var(--accent);">
                <span class="text-sm">Yes</span>
            </label>
            <label class="reg-check" style="flex:1;">
                <input type="radio" name="{{ $key }}" value="0" {{ (string) old($key) === '0' ? 'checked' : '' }} {{ $required ? 'required' : '' }} style="accent-color:var(--accent);">
                <span class="text-sm">No</span>
            </label>
        </div>
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

    @case('tshirt_size')
        @php $tshirtOptions = \App\Helpers\PlayerFormConfig::sizeOptions('tshirt_sizes', \App\Helpers\PlayerFormConfig::defaultTshirtSizes()); @endphp
        <label for="tshirt_size" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="tshirt_size" id="tshirt_size" class="reg-select" {{ $required ? 'required' : '' }}>
            <option value="">Select size</option>
            @foreach($tshirtOptions as $size)
                <option value="{{ $size }}" {{ old('tshirt_size') === $size ? 'selected' : '' }}>{{ $size }}</option>
            @endforeach
        </select>
        @error('tshirt_size')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('pant_size')
        @php $pantOptions = \App\Helpers\PlayerFormConfig::sizeOptions('pant_sizes', \App\Helpers\PlayerFormConfig::defaultPantSizes()); @endphp
        <label for="pant_size" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="pant_size" id="pant_size" class="reg-select" {{ $required ? 'required' : '' }}>
            <option value="">Select size</option>
            @foreach($pantOptions as $size)
                <option value="{{ $size }}" {{ old('pant_size') === $size ? 'selected' : '' }}>{{ $size }}</option>
            @endforeach
        </select>
        @error('pant_size')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('player_type')
        @if($playerTypes->count() > 0)
        <label for="player_type_id" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="player_type_id" id="player_type_id" class="reg-select" {{ $required ? 'required' : '' }}>
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
        <select name="batting_profile_id" id="batting_profile_id" class="reg-select" {{ $required ? 'required' : '' }}>
            <option value="">Select dominant hand</option>
            @foreach($battingProfiles as $profile)
                <option value="{{ $profile->id }}" {{ old('batting_profile_id') == $profile->id ? 'selected' : '' }}>{{ $profile->name ?? $profile->style }}</option>
            @endforeach
        </select>
        @error('batting_profile_id')<p class="reg-err">{{ $message }}</p>@enderror
        @endif
        @break

    @case('batting_mode')
        <label for="batting_mode" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="batting_mode" id="batting_mode" class="reg-select" {{ $required ? 'required' : '' }}>
            <option value="">Select batting mode</option>
            @foreach(['Aggressive Batsman','Defensive Batsman','Finisher','Anchor','Power Hitter'] as $mode)
                <option value="{{ $mode }}" {{ old('batting_mode') === $mode ? 'selected' : '' }}>{{ $mode }}</option>
            @endforeach
        </select>
        @error('batting_mode')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('preferred_batting_position')
        <label class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <p class="reg-hint mb-2">Select up to 3 positions</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            @foreach(['Opener','3','4','5','6','7','8',"I'm Flexible"] as $pos)
                <label class="reg-check">
                    <input type="checkbox" name="preferred_batting_positions[]" value="{{ $pos }}"
                           x-model="selectedPositions"
                           :disabled="!selectedPositions.includes('{{ $pos }}') && selectedPositions.length >= 3">
                    <span class="text-sm">{{ $pos }}</span>
                </label>
            @endforeach
        </div>
        @error('preferred_batting_positions')<p class="reg-err">{{ $message }}</p>@enderror
        @error('preferred_batting_positions.*')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('bowling_profile')
        @if($bowlingProfiles->count() > 0)
        <label for="bowling_profile_id" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="bowling_profile_id" id="bowling_profile_id" class="reg-select" {{ $required ? 'required' : '' }}>
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
        <input type="number" name="{{ $key }}" id="{{ $key }}" value="{{ old($key) }}" min="0" {{ $required ? 'required' : '' }} class="reg-input" placeholder="0">
        @error($key)<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('transportation')
        <label for="transportation_mode" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="transportation_mode" id="transportation_mode" class="reg-select" {{ $required ? 'required' : '' }}>
            <option value="">Select transportation to the venue</option>
            <option value="self" {{ old('transportation_mode') === 'self' ? 'selected' : '' }}>Self Transportation (Preferred by Franchises)</option>
            <option value="required" {{ old('transportation_mode') === 'required' ? 'selected' : '' }}>Transportation Required (Subject to Franchise Preferences)</option>
        </select>
        @error('transportation_mode')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('travel_plan')
        <label for="has_travel_plan" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <select name="has_travel_plan" id="has_travel_plan" class="reg-select" x-model="hasTravelPlan" {{ $required ? 'required' : '' }}>
            <option value="">Select</option>
            <option value="no" {{ old('has_travel_plan') === 'no' ? 'selected' : '' }}>No</option>
            <option value="yes" {{ old('has_travel_plan') === 'yes' ? 'selected' : '' }}>Yes</option>
        </select>
        @error('has_travel_plan')<p class="reg-err">{{ $message }}</p>@enderror
        <div x-show="hasTravelPlan === 'yes'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-3">
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
        <label class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        @include('public.registration.partials.player-image-upload', ['fieldConfig' => $fieldConfig, 'embedded' => true, 'fieldLabel' => $label])
        @break

    @case('terms_and_conditions')
        @php $hasTC = !empty($settings->terms_and_conditions_content ?? ''); @endphp
        {{-- Render the rich-text T&C authored in settings (headings, colours, fonts, lists). --}}
        <style>
            .tc-prose h1{font-size:1.6em;font-weight:700;margin:.6em 0 .3em;line-height:1.25;}
            .tc-prose h2{font-size:1.35em;font-weight:700;margin:.6em 0 .3em;line-height:1.3;}
            .tc-prose h3{font-size:1.15em;font-weight:600;margin:.5em 0 .3em;}
            .tc-prose h4,.tc-prose h5,.tc-prose h6{font-weight:600;margin:.5em 0 .3em;}
            .tc-prose p{margin:.5em 0;}
            .tc-prose ul{list-style:disc;padding-left:1.5em;margin:.5em 0;}
            .tc-prose ol{list-style:decimal;padding-left:1.5em;margin:.5em 0;}
            .tc-prose li{margin:.2em 0;}
            .tc-prose a{color:#2563eb;text-decoration:underline;}
            .tc-prose blockquote{border-left:3px solid #cbd5e1;padding-left:12px;color:#64748b;margin:.6em 0;}
            .tc-prose strong{font-weight:700;} .tc-prose em{font-style:italic;} .tc-prose u{text-decoration:underline;}
            .tc-prose img{max-width:100%;height:auto;}
            .tc-prose .ql-align-center{text-align:center;} .tc-prose .ql-align-right{text-align:right;} .tc-prose .ql-align-justify{text-align:justify;}
            .tc-prose .ql-font-serif{font-family:Georgia,'Times New Roman',serif;} .tc-prose .ql-font-monospace{font-family:Menlo,Consolas,monospace;}
            .tc-prose .ql-size-small{font-size:.75em;} .tc-prose .ql-size-large{font-size:1.5em;} .tc-prose .ql-size-huge{font-size:2.5em;}
        </style>
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
                {{-- Typed digital signature: captured once the applicant accepts --}}
                <div x-show="accepted" x-cloak class="mt-3">
                    <label for="consent_name" class="reg-label">Type your full name to sign <span class="reg-req">*</span></label>
                    <input type="text" name="consent_name" id="consent_name" value="{{ old('consent_name') }}"
                           class="reg-input" placeholder="Your full legal name" x-bind:required="accepted">
                    <p class="reg-hint">By typing your name you digitally sign and accept the Terms &amp; Conditions above. Your name, date &amp; time are recorded.</p>
                </div>
                @error('consent_name')<p class="reg-err">{{ $message }}</p>@enderror

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
                                 class="tc-prose flex-1 min-h-0 overflow-y-auto p-5 text-sm text-gray-700 dark:text-gray-300">{!! $settings->terms_and_conditions_content !!}</div>
                            <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between gap-2 flex-shrink-0">
                                <span class="text-xs text-gray-400" x-show="!readToEnd">Scroll to the end to accept.</span>
                                <span class="flex-1"></span>
                                <button type="button" @click="accepted = false; showTC = false"
                                        class="px-4 py-2 rounded-lg text-sm bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300 font-medium">Reject</button>
                                <button type="button" :disabled="!readToEnd" @click="accepted = true; showTC = false"
                                        class="px-4 py-2 rounded-lg text-sm font-semibold"
                                        :style="'background-color:#16a34a;color:#ffffff;' + (readToEnd ? '' : 'opacity:0.55;cursor:not-allowed;')">Accept</button>
                            </div>
                        </div>
                    </div>
                </template>
            @endif
        </div>
        @break

@endswitch
</div>
