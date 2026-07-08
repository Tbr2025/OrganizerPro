@php
    /** Editable (or locked/read-only) player field, styled like the admin detail cards.
     *  Expects: $key, $locked, $player + option lists from the parent view. */
    $labels = \App\Helpers\PlayerFormConfig::fieldLabels();
    $label = $fieldConfig[$key]['label'] ?? ($labels[$key] ?? ucwords(str_replace('_', ' ', $key)));

    // Map field key → the value currently on the player + a readable display.
    $rawFor = function ($k) use ($player, $countries, $visaList) {
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
            'bowling_profile' => $player->bowling_profile_id,
            'player_type' => $player->player_type_id,
            'is_wicket_keeper' => $player->is_wicket_keeper,
            'total_matches' => $player->total_matches,
            'total_runs' => $player->total_runs,
            'total_wickets' => $player->total_wickets,
            default => null,
        };
    };
    $displayFor = function ($k) use ($player, $countries, $visaList) {
        return match ($k) {
            'country' => $player->country ? ($countries[$player->country] ?? $player->country) : null,
            'visa_status' => $player->visa_status ? ($visaList[$player->visa_status] ?? $player->visa_status) : null,
            'location' => $player->location?->name,
            'playing_team' => $player->actualTeam?->name,
            'registration_team' => $player->team_name_ref,
            'batting_profile' => $player->battingProfile?->name ?? $player->battingProfile?->style,
            'bowling_profile' => $player->bowlingProfile?->name ?? $player->bowlingProfile?->style,
            'player_type' => $player->playerType?->name ?? $player->playerType?->type,
            'available_saturday' => is_null($player->available_saturday) ? null : ($player->available_saturday ? 'Yes' : 'No'),
            'available_sunday' => is_null($player->available_sunday) ? null : ($player->available_sunday ? 'Yes' : 'No'),
            'played_ys_ipl_s1' => is_null($player->played_ys_ipl_s1) ? null : ($player->played_ys_ipl_s1 ? 'Yes' : 'No'),
            'is_wicket_keeper' => $player->is_wicket_keeper ? 'Yes' : 'No',
            'date_of_birth' => optional($player->date_of_birth)->format('d M Y'),
            'visa_expiry' => optional($player->visa_expiry)->format('d M Y'),
            default => $player->{$k} ?? null,
        };
    };
    // Fields whose column differs from the key (for the hidden __present marker).
    $columnMap = [
        'mobile_number' => 'mobile_number_full', 'cricheroes_number' => 'cricheroes_number_full',
        'location' => 'location_id', 'playing_team' => 'actual_team_id', 'registration_team' => 'team_name_ref',
        'batting_profile' => 'batting_profile_id', 'bowling_profile' => 'bowling_profile_id',
        'player_type' => 'player_type_id',
    ];
    $col = $columnMap[$key] ?? $key;
    $val = $rawFor($key);
    $selectCls = 'w-full text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white';
    $inputCls = $selectCls;
@endphp

<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border {{ $locked ? 'border-green-400 dark:border-green-600' : 'border-transparent' }}">
    <div class="flex items-start justify-between gap-2 mb-1">
        <h4 class="text-[11px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $label }}</h4>
        @if($locked)
            <span class="text-[10px] font-semibold text-green-600 dark:text-green-400 whitespace-nowrap">✔ Verified · locked</span>
        @endif
    </div>

    @if($locked)
        @php $d = $displayFor($key); @endphp
        <p class="text-sm text-gray-900 dark:text-white break-words">{{ ($d === null || $d === '') ? '—' : $d }}</p>
    @else
        <input type="hidden" name="__present[]" value="{{ $col }}">
        @switch($key)
            @case('date_of_birth')
            @case('visa_expiry')
                <input type="date" name="{{ $col }}" value="{{ old($col, $val) }}" class="{{ $inputCls }}">
                @break
            @case('country')
                <select name="country" class="{{ $selectCls }}">
                    <option value="">— Select —</option>
                    @foreach($countries as $code => $cname)<option value="{{ $code }}" {{ old('country', $val) === $code ? 'selected' : '' }}>{{ $cname }}</option>@endforeach
                </select>
                @break
            @case('visa_status')
                <select name="visa_status" class="{{ $selectCls }}">
                    <option value="">— Select —</option>
                    @foreach($visaList as $v => $vl)<option value="{{ $v }}" {{ old('visa_status', $val) === $v ? 'selected' : '' }}>{{ $vl }}</option>@endforeach
                </select>
                @break
            @case('location')
                <select name="location_id" class="{{ $selectCls }}">
                    <option value="">— Select —</option>
                    @foreach($locations as $loc)<option value="{{ $loc->id }}" {{ (string) old('location_id', $val) === (string) $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>@endforeach
                </select>
                @break
            @case('playing_team')
                <select name="actual_team_id" class="{{ $selectCls }}">
                    <option value="">— Select —</option>
                    @foreach($actualTeams as $t)<option value="{{ $t->id }}" {{ (string) old('actual_team_id', $val) === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>@endforeach
                </select>
                @break
            @case('registration_team')
                <input type="text" name="team_name_ref" value="{{ old('team_name_ref', $val) }}" class="{{ $inputCls }}" placeholder="Team name">
                @break
            @case('tshirt_size')
                <select name="tshirt_size" class="{{ $selectCls }}"><option value="">— Select —</option>@foreach($tshirtOptions as $s)<option value="{{ $s }}" {{ old('tshirt_size', $val) === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach</select>
                @break
            @case('pant_size')
                <select name="pant_size" class="{{ $selectCls }}"><option value="">— Select —</option>@foreach($pantOptions as $s)<option value="{{ $s }}" {{ old('pant_size', $val) === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach</select>
                @break
            @case('batting_profile')
                <select name="batting_profile_id" class="{{ $selectCls }}"><option value="">— Select —</option>@foreach($battingProfiles as $b)<option value="{{ $b->id }}" {{ (string) old('batting_profile_id', $val) === (string) $b->id ? 'selected' : '' }}>{{ $b->name ?? $b->style }}</option>@endforeach</select>
                @break
            @case('bowling_profile')
                <select name="bowling_profile_id" class="{{ $selectCls }}"><option value="">— Select —</option>@foreach($bowlingProfiles as $b)<option value="{{ $b->id }}" {{ (string) old('bowling_profile_id', $val) === (string) $b->id ? 'selected' : '' }}>{{ $b->name ?? $b->style }}</option>@endforeach</select>
                @break
            @case('player_type')
                <select name="player_type_id" class="{{ $selectCls }}"><option value="">— Select —</option>@foreach($playerTypes as $pt)<option value="{{ $pt->id }}" {{ (string) old('player_type_id', $val) === (string) $pt->id ? 'selected' : '' }}>{{ $pt->name ?? $pt->type }}</option>@endforeach</select>
                @break
            @case('jersey_number')
            @case('total_matches')
            @case('total_runs')
            @case('total_wickets')
                <input type="number" name="{{ $col }}" value="{{ old($col, $val) }}" min="0" class="{{ $inputCls }}">
                @break
            @case('available_saturday')
            @case('available_sunday')
            @case('played_ys_ipl_s1')
                <div class="flex gap-4 pt-1">
                    <label class="flex items-center gap-1 text-sm"><input type="radio" name="{{ $col }}" value="1" {{ (string) old($col, $val) === '1' ? 'checked' : '' }}> Yes</label>
                    <label class="flex items-center gap-1 text-sm"><input type="radio" name="{{ $col }}" value="0" {{ (string) old($col, $val) === '0' || $val === false ? 'checked' : '' }}> No</label>
                </div>
                @break
            @case('is_wicket_keeper')
                <label class="flex items-center gap-2 text-sm pt-1"><input type="checkbox" name="wicket_keeper" value="1" {{ old('wicket_keeper', $val) ? 'checked' : '' }}> Yes</label>
                @break
            @case('employer_address')
                <textarea name="employer_address" rows="2" class="{{ $inputCls }}">{{ old('employer_address', $val) }}</textarea>
                @break
            @default
                <input type="text" name="{{ $col }}" value="{{ old($col, $val) }}" class="{{ $inputCls }}">
        @endswitch
    @endif
</div>
