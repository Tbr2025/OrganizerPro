@extends('public.tournament.layouts.app')

@section('title', 'Player Registration - ' . $tournament->name)

@push('styles')
<style>
    .reg-input,
    .reg-select {
        width: 100%;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 0.65rem;
        padding: 0.7rem 0.9rem;
        color: #fff;
        font-size: 0.95rem;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .reg-input::placeholder { color: rgba(255, 255, 255, 0.35); }
    .reg-input:focus,
    .reg-select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(var(--accent-rgb), 0.22);
        background: rgba(255, 255, 255, 0.08);
    }
    .reg-select option { background: var(--primary); color: #fff; }

    .reg-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #cbd5e1;
        margin-bottom: 0.45rem;
    }
    .reg-req { color: var(--accent); }
    .reg-hint { color: rgba(255, 255, 255, 0.45); font-size: .75rem; margin-top: .4rem; }
    .reg-err { color: #f87171; font-size: .8rem; margin-top: .4rem; }

    .reg-section {
        padding: 1.5rem; border-radius: 1rem; margin-bottom: 1.25rem;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.18);
        backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    }
    @media (min-width: 640px) { .reg-section { padding: 1.75rem; } }

    .reg-section-head { display: flex; align-items: center; gap: .85rem; margin-bottom: 1.4rem; }
    .reg-section-icon {
        width: 2.6rem; height: 2.6rem; border-radius: 0.8rem; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: rgba(var(--accent-rgb), 0.15);
        color: var(--accent); font-size: 1.05rem;
        border: 1px solid rgba(var(--accent-rgb), 0.25);
    }
    .reg-section-title { font-size: 1.15rem; font-weight: 700; line-height: 1.1; }
    .reg-section-sub { font-size: .78rem; color: rgba(255, 255, 255, 0.5); margin-top: .15rem; }

    .reg-check {
        display: flex; align-items: center; gap: .8rem;
        padding: .85rem 1rem; border-radius: .65rem; cursor: pointer;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: border-color .2s, background .2s;
    }
    .reg-check:hover { border-color: rgba(var(--accent-rgb), 0.45); background: rgba(var(--accent-rgb), 0.06); }
    /* Fully-styled checkbox so checked/unchecked is clearly visible on ANY theme */
    .reg-check input[type="checkbox"] {
        appearance: none; -webkit-appearance: none;
        width: 1.25rem; height: 1.25rem; flex-shrink: 0; cursor: pointer;
        border-radius: .35rem;
        border: 2px solid var(--accent);
        background: rgba(255, 255, 255, 0.92);
        position: relative; transition: background .15s, border-color .15s;
    }
    .reg-check input[type="checkbox"]:checked { background: var(--accent); border-color: var(--accent); }
    .reg-check input[type="checkbox"]:checked::after {
        content: ''; position: absolute; left: .38rem; top: .14rem;
        width: .32rem; height: .62rem; border: solid #fff; border-width: 0 2px 2px 0;
        transform: rotate(45deg);
    }
    .reg-check input[type="checkbox"]:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

    .reg-submit {
        width: 100%; border: none; cursor: pointer;
        padding: 1rem 1.5rem; border-radius: 0.8rem;
        font-family: 'Oswald', sans-serif; font-weight: 700; font-size: 1.05rem;
        letter-spacing: .03em; color: var(--primary);
        background: linear-gradient(135deg, var(--accent), var(--accent-dark));
        transition: transform .2s, box-shadow .2s; box-shadow: 0 10px 30px rgba(var(--accent-rgb), 0.25);
    }
    .reg-submit:hover { transform: translateY(-2px); box-shadow: 0 16px 40px rgba(var(--accent-rgb), 0.35); }

    /* Live validation styles */
    .reg-input.is-invalid,
    .reg-select.is-invalid { border-color: #f87171 !important; box-shadow: 0 0 0 3px rgba(248,113,113,0.2) !important; }
    .reg-input.is-valid,
    .reg-select.is-valid { border-color: #34d399 !important; }
    .live-err { color: #f87171; font-size: .8rem; margin-top: .35rem; display: none; }
    .live-err.show { display: block; }
</style>
@endpush

@include('public.registration.partials.registration-theme')

@section('content')
    @php
        $theme = ($settings ?? null) ? $settings->registrationTheme() : (new \App\Models\TournamentSetting())->registrationTheme();
        $layout = \App\Helpers\PlayerFormConfig::getFormLayout($settings ?? null, true);
        // Icon + subtitle keyed by the default section key (survives title renames).
        $sectionMeta = [
            'Basic Information'       => ['icon' => 'fa-id-card',        'sub' => 'Who you are and how to reach you'],
            'Visa & Employment'       => ['icon' => 'fa-passport',       'sub' => 'Your residency and work details'],
            'Availability'            => ['icon' => 'fa-calendar-check', 'sub' => 'When and where you can play'],
            'Jersey Information'       => ['icon' => 'fa-tshirt',         'sub' => 'What goes on your kit'],
            'Player Profile'          => ['icon' => 'fa-baseball-ball',   'sub' => 'Your playing style'],
            'Leather Ball Experience' => ['icon' => 'fa-chart-line',      'sub' => 'Your career numbers'],
            'Travel & Transportation' => ['icon' => 'fa-bus',            'sub' => 'Help us plan logistics'],
            'Player Photo'            => ['icon' => 'fa-camera',          'sub' => 'A clear, front-facing headshot'],
            'Terms & Conditions'      => ['icon' => 'fa-file-contract',   'sub' => 'Please review before submitting'],
        ];
    @endphp
    <div class="max-w-3xl mx-auto px-4 py-8 sm:py-10">

        {{-- Banner --}}
        <div class="reg-banner reveal">
            @if(($tournament->settings?->logo ?? $tournament->logo))
                <img src="{{ Storage::url($tournament->settings?->logo ?? $tournament->logo) }}" alt="{{ $tournament->name }}"
                     class="mx-auto mb-4" style="width:72px;height:72px;object-fit:contain;border-radius:1rem;background:rgba(255,255,255,0.12);padding:.5rem;">
            @endif
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold mb-3"
                  style="background:rgba(255,255,255,0.18);color:#fff;border:1px solid rgba(255,255,255,0.35);">
                <i class="fas fa-circle" style="font-size:.5rem;"></i> Registration Open
            </span>
            <h1 class="text-3xl font-bold">{{ $theme['banner_title'] ?: 'Player Registration' }}</h1>
            <p class="text-white/90 mt-2">{{ $theme['banner_subtitle'] ?: ('Join ' . $tournament->name) }}</p>
            @if($settings?->description)
                <p class="text-white/80 text-sm mt-3 max-w-lg mx-auto" style="line-height:1.6;">{!! nl2br(e($settings->description)) !!}</p>
            @endif
            <p class="text-white/70 text-sm mt-3 max-w-md mx-auto">
                <i class="fas fa-shield-alt mr-1"></i>
                Submit your details below — your application will be reviewed and you'll be notified by email.
            </p>
        </div>

        {{-- Validation summary --}}
        @if($errors->any())
            <div class="reg-section reveal" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.35);">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-red-400 mt-1"></i>
                    <div>
                        <p class="font-semibold text-red-300">Please fix the following:</p>
                        <ul class="list-disc list-inside text-sm text-red-200 mt-2 space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('public.tournament.registration.player.store', $tournament->slug) }}"
              enctype="multipart/form-data" x-data="{
                  hasTravelPlan: @js(old('has_travel_plan', '')),
                  visaStatus: @js(old('visa_status', '')),
                  selectedTeam: '{{ old('team_id') }}',
                  selectedPlayingTeam: @js(old('actual_team_id', '')),
                  selectedCountry: @js(old('country', ($defaultCountry ?: 'IN'))),
                  stateValue: @js(old('state')),
                  statesByCountry: @js(config('registration.states_by_country')),
                  dialCodesMap: @js(config('countries.dial_codes')),
                  dialCode: @js(old('mobile_country_code', config('countries.dial_codes')[$defaultPhoneCountry ?? $defaultCountry ?? 'IN'] ?? '+91')),
                  cricDialCode: @js(old('cricheroes_country_code', config('countries.dial_codes')[$defaultPhoneCountry ?? $defaultCountry ?? 'IN'] ?? '+91')),
                  selectedPositions: @js(old('preferred_batting_positions', [])),
                  get hasStates() { return Array.isArray(this.statesByCountry[this.selectedCountry]) && this.statesByCountry[this.selectedCountry].length > 0; },
              }">
            @csrf

            @php $allCustomFields = $tournament->customFields->where('visible', true)->where('form', 'player'); @endphp
            @foreach($layout as $section)
                @php
                    $meta = $sectionMeta[$section['key']] ?? ['icon' => 'fa-list-ul', 'sub' => ''];
                    $sectionCustom = $allCustomFields->where('section', $section['key']);
                @endphp
                @if(count($section['fields']) || $sectionCustom->count())
                <div class="reg-section glass reveal">
                    <div class="reg-section-head">
                        <div class="reg-section-icon"><i class="fas {{ $meta['icon'] }}"></i></div>
                        <div>
                            <div class="reg-section-title">{{ $section['title'] }}</div>
                            @if($meta['sub'])<div class="reg-section-sub">{{ $meta['sub'] }}</div>@endif
                        </div>
                    </div>

                    @if($section['key'] === 'Leather Ball Experience')
                        <div class="flex items-start gap-2 px-4 py-3 mb-4 rounded-lg border border-amber-500/30 bg-amber-500/10 text-amber-300 text-sm">
                            <i class="fas fa-exclamation-triangle mt-0.5 shrink-0"></i>
                            <span>Please provide accurate data. Players found submitting fake or misleading information will be rejected.</span>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        @foreach($section['fields'] as $fieldKey)
                            @include('public.registration.fields.player-field', ['key' => $fieldKey])
                        @endforeach
                        @foreach($sectionCustom as $cf)
                            @include('public.registration.fields.custom-field', ['cf' => $cf])
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach

            {{-- Turnstile CAPTCHA --}}
            @if(config('turnstile.site_key') && !app()->environment('local'))
            <div class="flex justify-center my-4">
                <div class="cf-turnstile" data-sitekey="{{ config('turnstile.site_key') }}" data-theme="dark"></div>
            </div>
            @endif

            {{-- Submit --}}
            <div class="reveal">
                <button type="submit" class="reg-submit btn-ripple">
                    <i class="fas fa-paper-plane mr-2"></i> Submit Registration
                </button>
                <p class="text-center text-gray-500 text-xs mt-3">
                    <i class="fas fa-lock mr-1"></i> Your information is only shared with the tournament organizers.
                </p>
            </div>
        </form>

        {{-- Contact Info --}}
        @include('public.registration.partials.contact-info')

        {{-- Back Link --}}
        <div class="text-center mt-8">
            <a href="{{ route('public.tournament.show', $tournament->slug) }}" class="text-gray-400 hover:text-white transition inline-flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back to Tournament
            </a>
        </div>
    </div>
@endsection

@if(config('turnstile.site_key') && !app()->environment('local'))
@push('scripts')
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endpush
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[method="POST"]');
    if (!form) return;

    function getOrCreateErr(field) {
        let err = field.parentElement.querySelector('.live-err');
        if (!err) {
            err = document.createElement('p');
            err.className = 'live-err';
            field.parentElement.appendChild(err);
        }
        return err;
    }

    function validateField(field) {
        // Skip hidden/disabled fields
        if (field.offsetParent === null || field.disabled) return;

        const err = getOrCreateErr(field);
        let msg = '';

        if (field.required && !field.value.trim()) {
            msg = 'This field is required.';
        } else if (field.type === 'email' && field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
            msg = 'Please enter a valid email address.';
        } else if (field.type === 'tel' && field.value && !/^[\d\s\-+()]{4,20}$/.test(field.value)) {
            msg = 'Please enter a valid phone number.';
        } else if (field.type === 'number' && field.value) {
            if (field.min !== '' && Number(field.value) < Number(field.min)) msg = 'Value must be at least ' + field.min + '.';
            if (field.max !== '' && Number(field.value) > Number(field.max)) msg = 'Value must be at most ' + field.max + '.';
        }

        if (msg) {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
            err.textContent = msg;
            err.classList.add('show');
        } else if (field.value.trim()) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            err.classList.remove('show');
        } else {
            field.classList.remove('is-invalid', 'is-valid');
            err.classList.remove('show');
        }
    }

    // Attach to all inputs and selects
    form.querySelectorAll('input, select, textarea').forEach(function (field) {
        if (field.type === 'hidden' || field.type === 'checkbox' || field.type === 'radio' || field.type === 'file') return;
        field.addEventListener('blur', function () { validateField(this); });
        field.addEventListener('change', function () { validateField(this); });
    });

    // Validate all on submit
    form.addEventListener('submit', function (e) {
        let hasError = false;
        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            if (field.type === 'hidden' || field.type === 'checkbox' || field.type === 'radio' || field.type === 'file') return;
            validateField(field);
            if (field.classList.contains('is-invalid')) hasError = true;
        });
        if (hasError) {
            e.preventDefault();
            const first = form.querySelector('.is-invalid');
            if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
});
</script>
@endpush
