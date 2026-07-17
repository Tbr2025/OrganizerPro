@php
    /** Admin-editable player field with verification toggle.
     *  Expects: $key, $player, $canVerify + option lists from the parent view. */
    $labels = \App\Helpers\PlayerFormConfig::fieldLabels();
    $label = ($fieldConfig[$key]['label'] ?? null) ?: ($labels[$key] ?? ucwords(str_replace('_', ' ', $key)));
    $isRequired = $fieldConfig[$key]['required'] ?? false;

    // Map field key → the DB column used for the value.
    $columnMap = [
        'mobile_number' => 'mobile_number_full', 'cricheroes_number' => 'cricheroes_number_full',
        'location' => 'location_id', 'playing_team' => 'actual_team_id', 'registration_team' => 'team_name_ref',
        'batting_profile' => 'batting_profile_id', 'bowling_profile' => 'bowling_profile_id',
        'player_type' => 'player_type_id',
    ];
    $col = $columnMap[$key] ?? $key;

    // Map field key → verified column name.
    $verifiedMap = [
        'first_name' => 'verified_name', 'last_name' => 'verified_name',
        'email' => 'verified_email',
        'mobile_number' => 'verified_mobile_number_full',
        'cricheroes_number' => 'verified_cricheroes_number_full',
        'cricheroes_profile_url' => 'verified_cricheroes_profile_url',
        'country' => 'verified_country', 'state' => 'verified_state',
        'location' => 'verified_location_id',
        'registration_team' => 'verified_team_id',
        'jersey_name' => 'verified_jersey_name', 'jersey_number' => 'verified_jersey_number',
        'batting_profile' => 'verified_batting_profile_id',
        'bowling_profile' => 'verified_bowling_profile_id',
        'player_type' => 'verified_player_type_id',
        'is_wicket_keeper' => 'verified_is_wicket_keeper',
        'transportation' => 'verified_transportation_required',
        'travel_plan' => 'verified_no_travel_plan',
        'total_matches' => 'verified_total_matches',
        'total_runs' => 'verified_total_runs',
        'total_wickets' => 'verified_total_wickets',
    ];
    $verifiedCol = $verifiedMap[$key] ?? null;
    $isVerified = $verifiedCol ? (bool) old($verifiedCol, $player->{$verifiedCol} ?? false) : false;

    // Get the current value for the field.
    $rawFor = function ($k) use ($player) {
        return match ($k) {
            'first_name' => $player->first_name,
            'last_name' => $player->last_name,
            'email' => $player->email,
            'date_of_birth' => optional($player->date_of_birth)->format('Y-m-d'),
            'country' => $player->country,
            'state' => $player->state,
            'mobile_number' => $player->mobile_number_full,
            'cricheroes_number' => $player->cricheroes_number_full,
            'cricheroes_profile_url' => $player->cricheroes_profile_url,
            'location' => $player->location_id,
            'registration_team' => $player->team_name_ref,
            'playing_team' => $player->actual_team_id,
            'visa_status' => $player->visa_status,
            'visa_expiry' => optional($player->visa_expiry)->format('Y-m-d'),
            'employer_name' => $player->employer_name,
            'employer_address' => $player->employer_address,
            'employer_position' => $player->employer_position,
            'available_saturday' => $player->available_saturday,
            'available_sunday' => $player->available_sunday,
            'played_ys_ipl_s1' => $player->played_ys_ipl_s1,
            'jersey_name' => $player->jersey_name,
            'jersey_number' => $player->jersey_number,
            'tshirt_size' => $player->tshirt_size,
            'pant_size' => $player->pant_size,
            'batting_profile' => $player->batting_profile_id,
            'batting_mode' => $player->batting_mode,
            'bowling_profile' => $player->bowling_profile_id,
            'player_type' => $player->player_type_id,
            'is_wicket_keeper' => $player->is_wicket_keeper,
            'total_matches' => $player->total_matches,
            'total_runs' => $player->total_runs,
            'total_wickets' => $player->total_wickets,
            default => $player->{$k} ?? null,
        };
    };
    $val = old($col, $rawFor($key));

    $inputCls = 'w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white';
    $selectCls = $inputCls;
@endphp

<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border {{ $isVerified ? 'border-green-400 dark:border-green-600' : 'border-gray-200 dark:border-gray-700' }}">
    <div class="flex items-start justify-between gap-2 mb-1.5">
        <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $label }} @if($isRequired)<span class="text-red-500">*</span>@endif</h4>
    </div>

    @switch($key)
        @case('date_of_birth')
        @case('visa_expiry')
            <input type="date" name="{{ $col }}" value="{{ $val }}" class="{{ $inputCls }}">
            @break

        @case('country')
            <select name="country" class="{{ $selectCls }}">
                <option value="">-- Select --</option>
                @foreach($countries as $code => $cname)<option value="{{ $code }}" {{ old('country', $val) === $code ? 'selected' : '' }}>{{ $cname }}</option>@endforeach
            </select>
            @break

        @case('state')
            <input type="text" name="state" value="{{ old('state', $val) }}" class="{{ $inputCls }}" placeholder="State / Province">
            @break

        @case('visa_status')
            <select name="visa_status" class="{{ $selectCls }}">
                <option value="">-- Select --</option>
                @foreach($visaList as $v => $vl)<option value="{{ $v }}" {{ old('visa_status', $val) === $v ? 'selected' : '' }}>{{ $vl }}</option>@endforeach
            </select>
            @break

        @case('location')
            <select name="location_id" class="{{ $selectCls }}">
                <option value="">-- Select --</option>
                @foreach($locations as $loc)<option value="{{ $loc->id }}" {{ (string) old('location_id', $val) === (string) $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>@endforeach
            </select>
            @break

        @case('playing_team')
            @php $auctionTeamIds = $actualTeams->filter(fn($t) => $t->tournament?->type === 'auction')->pluck('id')->toArray(); @endphp
            <div x-data="{ selectedTeam: '{{ old('actual_team_id', $val) }}', auctionIds: @js($auctionTeamIds) }">
                <select name="actual_team_id" class="{{ $selectCls }}" x-model="selectedTeam">
                    <option value="">-- Select --</option>
                    @foreach($actualTeams as $t)<option value="{{ $t->id }}" {{ (string) old('actual_team_id', $val) === (string) $t->id ? 'selected' : '' }}>{{ $t->name }} ({{ ucfirst($t->tournament?->type ?? 'unknown') }})</option>@endforeach
                </select>
                <p x-show="auctionIds.includes(parseInt(selectedTeam))" x-cloak class="mt-1.5 text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1">
                    <iconify-icon icon="lucide:alert-triangle" class="text-sm"></iconify-icon>
                    This team belongs to an auction tournament
                </p>
            </div>
            @break

        @case('registration_team')
            <div class="flex gap-2">
                <select name="team_id" class="{{ $selectCls }}">
                    <option value="">-- Select --</option>
                    @foreach($teams as $t)<option value="{{ $t->id }}" {{ (string) old('team_id', $player->team_id) === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>@endforeach
                </select>
                <input type="text" name="team_name_ref" value="{{ old('team_name_ref', $val) }}" class="{{ $inputCls }} w-32" placeholder="Or type">
            </div>
            @break

        @case('tshirt_size')
            @php
                $tshirtVal = old('tshirt_size', $val);
                $tshirtIsStandard = in_array($tshirtVal, $tshirtOptions, true);
                $tshirtIsOther = !$tshirtIsStandard && $tshirtVal !== null && $tshirtVal !== '';
            @endphp
            <div x-data="{ isOther: {{ $tshirtIsOther ? 'true' : 'false' }} }">
                <select name="tshirt_size" class="{{ $selectCls }}" x-on:change="isOther = ($event.target.value === 'Other')">
                    <option value="">-- Select --</option>
                    @foreach($tshirtOptions as $s)<option value="{{ $s }}" {{ $tshirtVal === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach
                    <option value="Other" {{ $tshirtIsOther ? 'selected' : '' }}>Other</option>
                </select>
                <div x-show="isOther" x-cloak class="mt-2">
                    <input type="text" name="tshirt_size_custom" class="{{ $inputCls }}" placeholder="Enter custom T-shirt size"
                           value="{{ $tshirtIsOther ? $tshirtVal : '' }}">
                </div>
            </div>
            @break

        @case('pant_size')
            @php
                $pantVal = old('pant_size', $val);
                $pantIsStandard = in_array($pantVal, $pantOptions, true);
                $pantMatchedOpt = $pantVal;
                if (!$pantIsStandard && $pantVal !== null && $pantVal !== '') {
                    foreach ($pantOptions as $opt) {
                        if (str_starts_with($opt, $pantVal . ' ')) { $pantMatchedOpt = $opt; $pantIsStandard = true; break; }
                    }
                }
                $pantIsOther = !$pantIsStandard && $pantVal !== null && $pantVal !== '';
            @endphp
            <div x-data="{ isOther: {{ $pantIsOther ? 'true' : 'false' }} }">
                <select name="pant_size" class="{{ $selectCls }}" x-on:change="isOther = ($event.target.value === 'Other')">
                    <option value="">-- Select --</option>
                    @foreach($pantOptions as $s)<option value="{{ $s }}" {{ $pantMatchedOpt === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach
                    <option value="Other" {{ $pantIsOther ? 'selected' : '' }}>Other</option>
                </select>
                <div x-show="isOther" x-cloak class="mt-2">
                    <input type="text" name="pant_size_custom" class="{{ $inputCls }}" placeholder="Enter custom pant size"
                           value="{{ $pantIsOther ? $pantVal : '' }}">
                </div>
            </div>
            @break

        @case('batting_profile')
            <select name="batting_profile_id" class="{{ $selectCls }}">
                <option value="">-- Select --</option>
                @foreach($battingProfiles as $b)<option value="{{ $b->id }}" {{ (string) old('batting_profile_id', $val) === (string) $b->id ? 'selected' : '' }}>{{ $b->name ?? $b->style }}</option>@endforeach
            </select>
            @break

        @case('batting_mode')
            <select name="batting_mode" class="{{ $selectCls }}">
                <option value="">-- Select --</option>
                @foreach($battingModes as $mode)<option value="{{ $mode }}" {{ old('batting_mode', $val) === $mode ? 'selected' : '' }}>{{ $mode }}</option>@endforeach
            </select>
            @break

        @case('preferred_batting_position')
            @php $selectedPositions = old('preferred_batting_positions', $player->preferred_batting_positions ?? []); @endphp
            <div class="grid grid-cols-4 gap-1 pt-1" x-data="{ selectedPositions: @js($selectedPositions) }">
                @foreach($battingPositions as $pos)
                    <label class="flex items-center gap-1 px-2 py-1 rounded border cursor-pointer transition-colors text-xs"
                        :class="selectedPositions.includes('{{ $pos }}') ? 'bg-blue-50 border-blue-400 dark:bg-blue-900/30 dark:border-blue-500' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'">
                        <input type="checkbox" name="preferred_batting_positions[]" value="{{ $pos }}"
                            x-model="selectedPositions"
                            :disabled="!selectedPositions.includes('{{ $pos }}') && selectedPositions.length >= 3"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-3 h-3">
                        <span class="text-gray-700 dark:text-gray-300">{{ $pos }}</span>
                    </label>
                @endforeach
            </div>
            @break

        @case('bowling_profile')
            <select name="bowling_profile_id" class="{{ $selectCls }}">
                <option value="">-- Select --</option>
                @foreach($bowlingProfiles as $b)<option value="{{ $b->id }}" {{ (string) old('bowling_profile_id', $val) === (string) $b->id ? 'selected' : '' }}>{{ $b->name ?? $b->style }}</option>@endforeach
            </select>
            @break

        @case('player_type')
            <select name="player_type_id" class="{{ $selectCls }}">
                <option value="">-- Select --</option>
                @foreach($playerTypes as $pt)<option value="{{ $pt->id }}" {{ (string) old('player_type_id', $val) === (string) $pt->id ? 'selected' : '' }}>{{ $pt->name ?? $pt->type }}</option>@endforeach
            </select>
            @break

        @case('is_wicket_keeper')
            <label class="flex items-center gap-2 text-sm pt-1">
                <input type="checkbox" name="is_wicket_keeper" value="1" {{ old('is_wicket_keeper', $val) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                Yes
            </label>
            @break

        @case('available_saturday')
        @case('available_sunday')
        @case('played_ys_ipl_s1')
            <div class="flex gap-4 pt-1">
                <label class="flex items-center gap-1 text-sm"><input type="radio" name="{{ $col }}" value="1" {{ (string) old($col, $val) === '1' ? 'checked' : '' }}> Yes</label>
                <label class="flex items-center gap-1 text-sm"><input type="radio" name="{{ $col }}" value="0" {{ (string) old($col, $val) === '0' || $val === false ? 'checked' : '' }}> No</label>
            </div>
            @break

        @case('transportation')
            @php $currentTransportMode = old('transportation_mode', $player->transportation_required ? 'required' : ($player->transportation_required === false ? 'self' : '')); @endphp
            <select name="transportation_mode" class="{{ $selectCls }}">
                <option value="">-- Select --</option>
                <option value="self" {{ $currentTransportMode === 'self' ? 'selected' : '' }}>Self Transportation (Preferred by Franchises)</option>
                <option value="required" {{ $currentTransportMode === 'required' ? 'selected' : '' }}>Transportation Required (Subject to Franchise Preferences)</option>
            </select>
            @break

        @case('travel_plan')
            @php $currentTravelPlan = old('has_travel_plan', $player->no_travel_plan ? 'no' : ($player->no_travel_plan === false ? 'yes' : '')); @endphp
            <div x-data="{ hasTravelPlan: '{{ $currentTravelPlan }}' }">
                <select name="has_travel_plan" class="{{ $selectCls }}" x-model="hasTravelPlan">
                    <option value="">-- Select --</option>
                    <option value="no">No</option>
                    <option value="yes">Yes</option>
                </select>
                <div x-show="hasTravelPlan === 'yes'" x-cloak class="mt-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-[10px] text-gray-500">From</label>
                        <input type="date" name="travel_date_from" value="{{ old('travel_date_from', $player->travel_date_from ? \Carbon\Carbon::parse($player->travel_date_from)->format('Y-m-d') : '') }}" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="text-[10px] text-gray-500">To</label>
                        <input type="date" name="travel_date_to" value="{{ old('travel_date_to', $player->travel_date_to ? \Carbon\Carbon::parse($player->travel_date_to)->format('Y-m-d') : '') }}" class="{{ $inputCls }}">
                    </div>
                </div>
            </div>
            @break

        @case('jersey_number')
        @case('total_matches')
        @case('total_runs')
        @case('total_wickets')
            <input type="number" name="{{ $col }}" value="{{ old($col, $val) }}" min="0" class="{{ $inputCls }}">
            @break

        @case('employer_address')
            <textarea name="employer_address" rows="2" class="{{ $inputCls }}">{{ old('employer_address', $val) }}</textarea>
            @break

        @case('mobile_number')
            <div class="flex items-start gap-2">
                <div class="w-2/5">
                    <select name="mobile_country_code_display" id="mobile_country_code_display"
                        class="{{ $selectCls }}" onchange="updateMobileFullNumber()">
                        @foreach(config('countries.dial_codes', []) as $code => $dial)
                            <option value="{{ $dial }}" {{ old('mobile_country_code', $player->mobile_country_code ?? '') == $dial ? 'selected' : '' }}>
                                {{ config('countries.list.' . $code) }} ({{ $dial }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="w-3/5">
                    <input type="text" id="mobile_national_display"
                        value="{{ old('mobile_national_number', $player->mobile_national_number ?? $player->mobile_number_full) }}"
                        placeholder="Mobile Number" class="{{ $inputCls }}" oninput="updateMobileFullNumber()">
                </div>
            </div>
            <input type="hidden" name="mobile_number_full" id="mobile_number_full" value="{{ old('mobile_number_full', $player->mobile_number_full) }}">
            @break

        @case('cricheroes_number')
            <div class="flex items-start gap-2">
                <div class="w-2/5">
                    <select name="cricheroes_country_code_display" id="cricheroes_country_code_display"
                        class="{{ $selectCls }}" onchange="updateCricheroesFullNumber()">
                        @foreach(config('countries.dial_codes', []) as $code => $dial)
                            <option value="{{ $dial }}" {{ old('cricheroes_country_code', $player->cricheroes_country_code ?? '') == $dial ? 'selected' : '' }}>
                                {{ config('countries.list.' . $code) }} ({{ $dial }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="w-3/5">
                    <input type="text" id="cricheroes_national_display"
                        value="{{ old('cricheroes_national_number', $player->cricheroes_national_number ?? $player->cricheroes_number_full) }}"
                        placeholder="CricHeroes Number" class="{{ $inputCls }}" oninput="updateCricheroesFullNumber()">
                </div>
            </div>
            <input type="hidden" name="cricheroes_number_full" id="cricheroes_number_full" value="{{ old('cricheroes_number_full', $player->cricheroes_number_full) }}">
            @break

        @case('cricheroes_profile_url')
            <input type="url" name="cricheroes_profile_url" value="{{ old('cricheroes_profile_url', $val) }}" class="{{ $inputCls }}" placeholder="https://cricheroes.com/...">
            @break

        @default
            <input type="text" name="{{ $col }}" value="{{ old($col, $val) }}" class="{{ $inputCls }}">
    @endswitch

    @error($col)<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
</div>
