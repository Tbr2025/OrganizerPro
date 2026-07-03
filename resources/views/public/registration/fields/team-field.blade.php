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
        @php
            $teamImgText = trim((string) ($settings->team_photo_guidelines ?? ''));
            $teamImgLines = $teamImgText !== '' ? preg_split('/\r\n|\r|\n/', $teamImgText) : [];
            $teamSampleUrl = $settings?->team_photo_sample_url;
        @endphp
        @if(count($teamImgLines) || $teamSampleUrl)
            <div class="mb-2 flex items-start gap-3 p-3 rounded-lg" style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.18);">
                @if($teamSampleUrl)
                    <img src="{{ $teamSampleUrl }}" alt="Sample" class="w-16 h-16 rounded object-contain flex-shrink-0" style="background:rgba(255,255,255,0.12);">
                @endif
                <div class="text-xs text-white/90">
                    <p class="font-semibold text-white mb-1">Image Guidelines</p>
                    <ul class="space-y-0.5 list-disc list-inside text-white/80">
                        @forelse($teamImgLines as $line)
                            @if(trim($line) !== '')<li>{{ trim($line) }}</li>@endif
                        @empty
                            <li>Square logo, plain/transparent background</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endif
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
        @php $hasTC = !empty($settings->team_terms_and_conditions_content ?? ''); @endphp
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
            <label class="reg-check" @if($hasTC) @click.prevent="openTC()" @endif>
                <input type="checkbox" name="terms_and_conditions" id="terms_and_conditions" value="1"
                       x-model="accepted" {{ $required ? 'required' : '' }}
                       @if($hasTC) tabindex="-1" style="pointer-events:none" @endif>
                <span class="text-sm">{!! $label !!} {!! $reqMark !!}</span>
            </label>
            @error('terms_and_conditions')<p class="reg-err">{{ $message }}</p>@enderror

            @if($hasTC)
                {{-- Typed digital signature: captured once the T&C is accepted --}}
                <div x-show="accepted" x-cloak class="mt-3">
                    <label for="consent_name" class="reg-label">Type your full name to sign <span class="reg-req">*</span></label>
                    <input type="text" name="consent_name" id="consent_name" value="{{ old('consent_name') }}"
                           class="reg-input" placeholder="Your full legal name" x-bind:required="accepted">
                    <p class="reg-hint">By typing your name you digitally sign and accept the Terms &amp; Conditions above. Your name, date &amp; time are recorded.</p>
                </div>
                @error('consent_name')<p class="reg-err">{{ $message }}</p>@enderror

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
                                 class="flex-1 min-h-0 overflow-y-auto p-5 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $settings->team_terms_and_conditions_content }}</div>
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
