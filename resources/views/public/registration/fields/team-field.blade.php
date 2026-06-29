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
        @php $hasTC = !empty($settings->terms_and_conditions_content ?? ''); @endphp
        <div x-data="{ showTC: false, accepted: {{ old('terms_and_conditions') ? 'true' : 'false' }} }">
            <label class="reg-check" @if($hasTC) @click.prevent="showTC = true" @endif>
                <input type="checkbox" name="terms_and_conditions" id="terms_and_conditions" value="1"
                       x-model="accepted" {{ $required ? 'required' : '' }}
                       @if($hasTC) tabindex="-1" style="pointer-events:none" @endif>
                <span class="text-sm">{!! $label !!} {!! $reqMark !!}</span>
            </label>
            @error('terms_and_conditions')<p class="reg-err">{{ $message }}</p>@enderror

            @if($hasTC)
                <template x-teleport="body">
                    <div x-show="showTC" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                         style="background:rgba(0,0,0,0.6);" @keydown.escape.window="showTC = false">
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col overflow-hidden"
                             @click.outside="showTC = false">
                            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Terms &amp; Conditions</h3>
                                <button type="button" @click="showTC = false" class="text-gray-400 hover:text-gray-700 dark:hover:text-white text-xl leading-none">&times;</button>
                            </div>
                            <div class="p-5 overflow-y-auto text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $settings->terms_and_conditions_content }}</div>
                            <div class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-2">
                                <button type="button" @click="showTC = false"
                                        class="px-4 py-2 rounded-lg text-sm bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200">Close</button>
                                <button type="button" @click="accepted = true; showTC = false"
                                        class="px-4 py-2 rounded-lg text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">I Accept</button>
                            </div>
                        </div>
                    </div>
                </template>
            @endif
        </div>
        @break

@endswitch
</div>
