<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration update</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    @php
        $isRejected = $status === 'rejected';
        $primaryColor = $isRejected ? '#b91c1c' : ($tournament->settings?->primary_color ?? '#1a56db');
        $appLogoRaw = config('settings.site_logo_lite') ?: 'images/logo/lara-dashboard.png';
        $appLogo = \Illuminate\Support\Str::startsWith($appLogoRaw, ['http://', 'https://']) ? $appLogoRaw : asset(ltrim($appLogoRaw, '/'));
        $tournamentLogo = $tournament?->settings?->logo_url;
        $heading = $isRejected ? 'Registration update' : 'You are on the waitlist';
    @endphp

    {{-- Header (logo sits on a white chip so it always contrasts with the banner) --}}
    <div style="background: {{ $primaryColor }}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <div style="margin: 0 auto 15px;">
            <img src="{{ $appLogo }}" alt="{{ config('app.name') }}" style="height: 56px; max-width: 160px; object-fit: contain; background: #ffffff; border-radius: 8px; padding: 8px; vertical-align: middle;">
            @if($tournamentLogo)
                <img src="{{ $tournamentLogo }}" alt="{{ $tournament->name }}" style="height: 56px; width: 56px; object-fit: contain; background: #ffffff; border-radius: 50%; padding: 6px; vertical-align: middle; margin-left: 12px;">
            @endif
        </div>
        <h1 style="color: #ffffff; margin: 0; font-size: 24px;">{{ $heading }}</h1>
        <p style="color: rgba(255,255,255,0.85); margin: 10px 0 0 0;">{{ $tournament->name }}</p>
    </div>

    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none; border-radius: 0 0 10px 10px;">
        <p style="margin: 0 0 20px 0; font-size: 16px;">Dear <strong>{{ $recipientName }}</strong>,</p>

        @if($isRejected)
            <p style="margin: 0 0 16px 0;">Thank you for your interest in <strong>{{ $tournament->name }}</strong> and for taking the time to register.</p>
            <p style="margin: 0 0 16px 0;">We're sorry for the inconvenience — after careful review, you have <strong>not been selected</strong> for this tournament on this occasion.</p>
            <p style="margin: 0 0 16px 0;">We genuinely appreciate your participation and hope to see you register again for future tournaments.</p>
        @else
            <p style="margin: 0 0 16px 0;">Thank you for registering for <strong>{{ $tournament->name }}</strong>.</p>
            <p style="margin: 0 0 16px 0;">Your application has been placed <strong>in the queue (waitlist)</strong>. If a place becomes available, we'll be in touch with the next steps — no further action is needed from you right now.</p>
        @endif

        @if($remarks)
            <div style="background: white; border-radius: 8px; padding: 15px; margin: 20px 0; border-left: 4px solid {{ $primaryColor }};">
                <p style="margin: 0; color: #495057; font-size: 14px;"><strong>Note from the organizers:</strong> {{ $remarks }}</p>
            </div>
        @endif
    </div>

    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">This is an automated message from {{ config('app.name') }}.</p>
    </div>
</body>
</html>
