@php
    $t = ($settings ?? null) ? $settings->registrationTheme() : (new \App\Models\TournamentSetting())->registrationTheme();
    $rgb = fn ($hex) => \App\Services\ThemeColorService::hexToRgb($hex);
    // icon tint (rgba) only computed from a hex value
    $iconRgb = (is_string($t['icon_color']) && str_starts_with($t['icon_color'], '#')) ? $rgb($t['icon_color']) : '251, 191, 36';
    // Hero banner image: prefer the Registration-Theme "Banner Image", then fall
    // back to the Branding "Hero Background Image" so either upload lights up the hero.
    $storageUrl = fn ($p) => \Illuminate\Support\Facades\Storage::disk('public')->url($p);
    $heroImage = null;
    if (($settings ?? null)) {
        if (!empty($t['banner_image']))        $heroImage = $storageUrl($t['banner_image']);
        elseif (!empty($settings->background_image)) $heroImage = $storageUrl($settings->background_image);
    }
@endphp
@push('styles')
<style>
    /* ── Registration page theme (scoped to this page) ── */
    body { background: linear-gradient(160deg, {{ $t['page_bg_from'] }} 0%, {{ $t['page_bg_to'] }} 100%) !important; }

    .reg-section { background: {{ $t['card_bg'] }}; }
    .reg-section-icon {
        color: {{ $t['icon_color'] }};
        background: rgba({{ $iconRgb }}, 0.15);
        border-color: rgba({{ $iconRgb }}, 0.30);
    }
    .reg-label { color: {{ $t['label_color'] }}; }
    .reg-req { color: {{ $t['icon_color'] }}; }

    .reg-submit {
        background: linear-gradient(135deg, {{ $t['button_gradient_from'] }}, {{ $t['button_gradient_to'] }}) !important;
        box-shadow: 0 10px 30px rgba({{ $iconRgb }}, 0.25);
    }

    .footer-wrapper {
        background: linear-gradient(135deg, {{ $t['footer_gradient_from'] }} 0%, {{ $t['footer_gradient_to'] }} 100%) !important;
    }

    /* ── Hero banner ── */
    .reg-banner {
        position: relative; overflow: hidden; border-radius: 1.25rem; margin-bottom: 1.5rem;
        padding: 3.5rem 1.5rem; text-align: center;
        min-height: 240px;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        border: 1px solid rgba(255,255,255,0.10);
        box-shadow: 0 20px 45px rgba(0,0,0,0.35);
        @if($heroImage)
            background-image:
                linear-gradient(180deg, rgba(0,0,0,0.35) 0%, rgba(0,0,0,0.55) 55%, rgba(0,0,0,0.72) 100%),
                url('{{ $heroImage }}');
            background-size: cover; background-position: center;
        @else
            background: linear-gradient(135deg, {{ $t['header_gradient_from'] }}, {{ $t['header_gradient_to'] }});
        @endif
    }
    /* Subtle accent glow along the bottom edge of the hero */
    .reg-banner::after {
        content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 4px;
        background: linear-gradient(90deg, {{ $t['button_gradient_from'] }}, {{ $t['button_gradient_to'] }});
    }
    .reg-banner > * { position: relative; z-index: 1; }
    .reg-banner h1 { color: #fff; text-shadow: 0 2px 12px rgba(0,0,0,0.45); }
    .reg-banner p  { text-shadow: 0 1px 8px rgba(0,0,0,0.45); }
    @media (max-width: 640px) {
        .reg-banner { padding: 2.5rem 1.25rem; min-height: 200px; border-radius: 1rem; }
        .reg-banner h1 { font-size: 1.6rem; }
    }
</style>
@endpush
