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

    .reg-section { padding: 1.5rem; border-radius: 1rem; margin-bottom: 1.25rem; }
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
    .reg-check input[type="checkbox"] { width: 1.15rem; height: 1.15rem; accent-color: var(--accent); flex-shrink: 0; }

    .reg-submit {
        width: 100%; border: none; cursor: pointer;
        padding: 1rem 1.5rem; border-radius: 0.8rem;
        font-family: 'Oswald', sans-serif; font-weight: 700; font-size: 1.05rem;
        letter-spacing: .03em; color: var(--primary);
        background: linear-gradient(135deg, var(--accent), var(--accent-dark));
        transition: transform .2s, box-shadow .2s; box-shadow: 0 10px 30px rgba(var(--accent-rgb), 0.25);
    }
    .reg-submit:hover { transform: translateY(-2px); box-shadow: 0 16px 40px rgba(var(--accent-rgb), 0.35); }
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
                  noTravel: {{ old('no_travel_plan') ? 'true' : 'false' }},
                  visaStatus: @js(old('visa_status', '')),
                  selectedTeam: '{{ old('team_id') }}',
                  selectedCountry: @js(old('country', $defaultCountry ?? '')),
                  stateValue: @js(old('state')),
                  statesByCountry: @js(config('registration.states_by_country')),
                  get hasStates() { return Array.isArray(this.statesByCountry[this.selectedCountry]) && this.statesByCountry[this.selectedCountry].length > 0; },
              }">
            @csrf

            @foreach($layout as $section)
                @php $meta = $sectionMeta[$section['key']] ?? ['icon' => 'fa-list-ul', 'sub' => '']; @endphp
                @if(count($section['fields']))
                <div class="reg-section glass reveal">
                    <div class="reg-section-head">
                        <div class="reg-section-icon"><i class="fas {{ $meta['icon'] }}"></i></div>
                        <div>
                            <div class="reg-section-title">{{ $section['title'] }}</div>
                            @if($meta['sub'])<div class="reg-section-sub">{{ $meta['sub'] }}</div>@endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        @foreach($section['fields'] as $fieldKey)
                            @include('public.registration.fields.player-field', ['key' => $fieldKey])
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach

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

        {{-- Back Link --}}
        <div class="text-center mt-8">
            <a href="{{ route('public.tournament.show', $tournament->slug) }}" class="text-gray-400 hover:text-white transition inline-flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back to Tournament
            </a>
        </div>
    </div>
@endsection
