@php
    $contactEmail = $settings->contact_email ?? null;
    $contactPhone = $settings->contact_phone ?? null;
    $whatsapp = $settings->whatsapp_contact ?? null;
    $hasContact = $contactEmail || $contactPhone || $whatsapp;
@endphp

@if($hasContact)
<div class="reg-section glass reveal" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.12);margin-top:2rem;">
    <div class="reg-section-head">
        <div class="reg-section-icon"><i class="fas fa-headset"></i></div>
        <div>
            <div class="reg-section-title">Need Help?</div>
            <div class="reg-section-sub">Contact the organizers for any queries</div>
        </div>
    </div>
    <p style="color:rgba(255,255,255,0.6);font-size:.88rem;margin-bottom:1rem;">
        If you have any questions or need assistance with your registration, feel free to reach out to the tournament organizers.
    </p>
    <div class="flex flex-wrap gap-3">
        @if($contactEmail)
        <a href="mailto:{{ $contactEmail }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition"
           style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);color:#fff;">
            <i class="fas fa-envelope" style="color:var(--accent);"></i> {{ $contactEmail }}
        </a>
        @endif
        @if($contactPhone)
        <a href="tel:{{ $contactPhone }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition"
           style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);color:#fff;">
            <i class="fas fa-phone" style="color:var(--accent);"></i> {{ $contactPhone }}
        </a>
        @endif
        @if($whatsapp)
        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $whatsapp) }}" target="_blank"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition"
           style="background:rgba(37,211,102,0.15);border:1px solid rgba(37,211,102,0.3);color:#25d366;">
            <i class="fab fa-whatsapp"></i> WhatsApp
        </a>
        @endif
    </div>
</div>
@endif
