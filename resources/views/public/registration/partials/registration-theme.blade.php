@php
    $t = ($settings ?? null) ? $settings->registrationTheme() : (new \App\Models\TournamentSetting())->registrationTheme();
    $rgb = fn ($hex) => \App\Services\ThemeColorService::hexToRgb($hex);
    // icon tint (rgba) only computed from a hex value
    $iconRgb = (is_string($t['icon_color']) && str_starts_with($t['icon_color'], '#')) ? $rgb($t['icon_color']) : '251, 191, 36';
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

    /* Themed banner */
    .reg-banner {
        position: relative; overflow: hidden; border-radius: 1rem; margin-bottom: 1.25rem;
        padding: 2.5rem 1.5rem; text-align: center;
        @if($t['banner_image'])
            background-image: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.65)), url('{{ \Illuminate\Support\Facades\Storage::disk('public')->url($t['banner_image']) }}');
            background-size: cover; background-position: center;
        @else
            background: linear-gradient(135deg, {{ $t['header_gradient_from'] }}, {{ $t['header_gradient_to'] }});
        @endif
    }
    .reg-banner h1 { color: #fff; text-shadow: 0 2px 12px rgba(0,0,0,0.35); }
</style>
@endpush
