@php
    /** @var string $key */
    $cfg = $teamFieldConfig[$key] ?? ['label' => $key, 'required' => false];
    $label = $cfg['label'] ?? $key;
    $required = $cfg['required'] ?? false;
    $reqMark = $required ? '<span class="reg-req">*</span>' : '';
    $fullWidth = in_array($key, ['team_logo', 'team_description', 'terms_and_conditions'], true);
@endphp

<div class="{{ $fullWidth ? 'md:col-span-2' : '' }}">
@switch($key)

    @case('team_name')
        <label for="team_name" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="text" name="team_name" id="team_name" value="{{ old('team_name') }}" {{ $required ? 'required' : '' }} class="reg-input" placeholder="Enter team name">
        @error('team_name')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('team_short_name')
        <label for="team_short_name" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="text" name="team_short_name" id="team_short_name" value="{{ old('team_short_name') }}" maxlength="10" {{ $required ? 'required' : '' }} class="reg-input" placeholder="e.g. SRH, MI, CSK">
        @error('team_short_name')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('team_logo')
        <label for="team_logo" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="file" name="team_logo" id="team_logo" accept="image/png,image/jpeg,image/jpg" {{ $required ? 'required' : '' }}
               class="reg-input" style="padding:.55rem .9rem;">
        <p class="reg-hint">PNG or JPG, max 2MB</p>
        @error('team_logo')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('team_description')
        <label for="team_description" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <textarea name="team_description" id="team_description" rows="3" {{ $required ? 'required' : '' }} class="reg-input" placeholder="Brief description about your team (optional)">{{ old('team_description') }}</textarea>
        @error('team_description')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('captain_name')
        <label for="captain_name" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="text" name="captain_name" id="captain_name" value="{{ old('captain_name') }}" {{ $required ? 'required' : '' }} class="reg-input" placeholder="Full name">
        @error('captain_name')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('captain_email')
        <label for="captain_email" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="email" name="captain_email" id="captain_email" value="{{ old('captain_email') }}" {{ $required ? 'required' : '' }} class="reg-input" placeholder="manager@email.com">
        @error('captain_email')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('captain_phone')
        <label for="captain_phone" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="tel" name="captain_phone" id="captain_phone" value="{{ old('captain_phone') }}" {{ $required ? 'required' : '' }} class="reg-input" placeholder="+971 50 123 4567">
        @error('captain_phone')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('vice_captain_name')
        <label for="vice_captain_name" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="text" name="vice_captain_name" id="vice_captain_name" value="{{ old('vice_captain_name') }}" {{ $required ? 'required' : '' }} class="reg-input" placeholder="Full name">
        @error('vice_captain_name')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('vice_captain_email')
        <label for="vice_captain_email" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="email" name="vice_captain_email" id="vice_captain_email" value="{{ old('vice_captain_email') }}" {{ $required ? 'required' : '' }} class="reg-input" placeholder="owner@email.com">
        @error('vice_captain_email')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('vice_captain_phone')
        <label for="vice_captain_phone" class="reg-label">{!! $label !!} {!! $reqMark !!}</label>
        <input type="tel" name="vice_captain_phone" id="vice_captain_phone" value="{{ old('vice_captain_phone') }}" {{ $required ? 'required' : '' }} class="reg-input" placeholder="+971 50 123 4567">
        @error('vice_captain_phone')<p class="reg-err">{{ $message }}</p>@enderror
        @break

    @case('terms_and_conditions')
        @if(!empty($settings->terms_and_conditions_content ?? ''))
        <div x-data="{ showTC: false }" class="mb-4">
            <button type="button" @click="showTC = !showTC" class="accent-link text-sm underline mb-3">
                <i class="fas fa-eye mr-1"></i> View Terms &amp; Conditions
            </button>
            <div x-show="showTC" x-cloak class="p-4 rounded-lg text-sm text-gray-300 max-h-48 overflow-y-auto whitespace-pre-wrap" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);">{{ $settings->terms_and_conditions_content }}</div>
        </div>
        @endif
        <label class="reg-check">
            <input type="checkbox" name="terms_and_conditions" id="terms_and_conditions" value="1" {{ old('terms_and_conditions') ? 'checked' : '' }} {{ $required ? 'required' : '' }}>
            <span class="text-sm">{!! $label !!} {!! $reqMark !!}</span>
        </label>
        @error('terms_and_conditions')<p class="reg-err">{{ $message }}</p>@enderror
        @break

@endswitch
</div>
