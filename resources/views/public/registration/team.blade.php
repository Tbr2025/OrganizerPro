@extends('public.tournament.layouts.app')

@section('title', 'Team Registration - ' . $tournament->name)

@push('styles')
<style>
    .reg-input, .reg-select {
        width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12);
        border-radius: 0.65rem; padding: 0.7rem 0.9rem; color: #fff; font-size: 0.95rem;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .reg-input::placeholder { color: rgba(255,255,255,0.35); }
    .reg-input:focus, .reg-select:focus {
        outline: none; border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(var(--accent-rgb), 0.22); background: rgba(255,255,255,0.08);
    }
    .reg-label { display:block; font-size:0.72rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:#cbd5e1; margin-bottom:0.45rem; }
    .reg-req { color: var(--accent); }
    .reg-hint { color: rgba(255,255,255,0.45); font-size:.75rem; margin-top:.4rem; }
    .reg-err { color:#f87171; font-size:.8rem; margin-top:.4rem; }
    .reg-section { padding:1.5rem; border-radius:1rem; margin-bottom:1.25rem; }
    @media (min-width:640px){ .reg-section{ padding:1.75rem; } }
    .reg-section-head { display:flex; align-items:center; gap:.85rem; margin-bottom:1.4rem; }
    .reg-section-icon { width:2.6rem; height:2.6rem; border-radius:0.8rem; flex-shrink:0; display:flex; align-items:center; justify-content:center; background:rgba(var(--accent-rgb),0.15); color:var(--accent); font-size:1.05rem; border:1px solid rgba(var(--accent-rgb),0.25); }
    .reg-section-title { font-size:1.15rem; font-weight:700; line-height:1.1; }
    .reg-section-sub { font-size:.78rem; color:rgba(255,255,255,0.5); margin-top:.15rem; }
    .reg-check { display:flex; align-items:center; gap:.8rem; padding:.85rem 1rem; border-radius:.65rem; cursor:pointer; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); transition:border-color .2s, background .2s; }
    .reg-check:hover { border-color:rgba(var(--accent-rgb),0.45); background:rgba(var(--accent-rgb),0.06); }
    .reg-check input[type="checkbox"] {
        appearance: none; -webkit-appearance: none;
        width:1.25rem; height:1.25rem; flex-shrink:0; cursor:pointer; border-radius:.35rem;
        border:2px solid var(--accent); background:rgba(255,255,255,0.92); position:relative;
    }
    .reg-check input[type="checkbox"]:checked { background:var(--accent); border-color:var(--accent); }
    .reg-check input[type="checkbox"]:checked::after {
        content:''; position:absolute; left:.38rem; top:.14rem; width:.32rem; height:.62rem;
        border:solid #fff; border-width:0 2px 2px 0; transform:rotate(45deg);
    }
    .reg-submit { width:100%; border:none; cursor:pointer; padding:1rem 1.5rem; border-radius:0.8rem; font-family:'Oswald',sans-serif; font-weight:700; font-size:1.05rem; letter-spacing:.03em; color:var(--primary); background:linear-gradient(135deg,var(--accent),var(--accent-dark)); transition:transform .2s, box-shadow .2s; box-shadow:0 10px 30px rgba(var(--accent-rgb),0.25); }
    .reg-submit:hover { transform:translateY(-2px); box-shadow:0 16px 40px rgba(var(--accent-rgb),0.35); }
</style>
@endpush

@include('public.registration.partials.registration-theme')

@section('content')
    @php
        $theme = ($settings ?? null) ? $settings->registrationTheme() : (new \App\Models\TournamentSetting())->registrationTheme();
        $layout = \App\Helpers\TeamFormConfig::getFormLayout($settings ?? null, true);
        $sectionMeta = [
            'Team Information'     => ['icon' => 'fa-shield-alt',    'sub' => 'Tell us about your team'],
            'Team Manager Details' => ['icon' => 'fa-user-tie',      'sub' => 'Primary contact for the team'],
            'Team Owner Details'   => ['icon' => 'fa-crown',         'sub' => 'Team owner (optional)'],
            'Terms & Conditions'   => ['icon' => 'fa-file-contract', 'sub' => 'Please review before submitting'],
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
            <h1 class="text-3xl font-bold">{{ $theme['banner_title'] ?: 'Team Registration' }}</h1>
            <p class="text-white/90 mt-2">{{ $theme['banner_subtitle'] ?: ('Register your team for ' . $tournament->name) }}</p>
            @if($settings?->description)
                <p class="text-white/80 text-sm mt-3 max-w-lg mx-auto" style="line-height:1.6;">{!! nl2br(e($settings->description)) !!}</p>
            @endif
        </div>

        {{-- Validation summary --}}
        @if($errors->any())
            <div class="reg-section reveal" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.35);">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-red-400 mt-1"></i>
                    <div>
                        <p class="font-semibold text-red-300">Please fix the following:</p>
                        <ul class="list-disc list-inside text-sm text-red-200 mt-2 space-y-1">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('public.tournament.registration.team.store', $tournament->slug) }}" enctype="multipart/form-data">
            @csrf

            @php $allCustomFields = $tournament->customFields->where('visible', true)->where('form', 'team'); @endphp
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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        @foreach($section['fields'] as $fieldKey)
                            @include('public.registration.fields.team-field', ['key' => $fieldKey])
                        @endforeach
                        @foreach($sectionCustom as $cf)
                            @include('public.registration.fields.custom-field', ['cf' => $cf])
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach

            {{-- Submit --}}
            <div class="reveal">
                <button type="submit" class="reg-submit btn-ripple">
                    <i class="fas fa-paper-plane mr-2"></i> Submit Team Registration
                </button>
            </div>
        </form>

        {{-- Contact Info --}}
        @include('public.registration.partials.contact-info')

        <div class="text-center mt-8">
            <a href="{{ route('public.tournament.show', $tournament->slug) }}" class="text-gray-400 hover:text-white transition inline-flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back to Tournament
            </a>
        </div>
    </div>
@endsection
