<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Review</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    @php
        $primaryColor = $tournament->settings?->primary_color ?? '#1a56db';
        // Main application logo as an absolute URL (email clients need absolute src).
        $appLogoRaw = config('settings.site_logo_lite') ?: 'images/logo/lara-dashboard.png';
        $appLogo = \Illuminate\Support\Str::startsWith($appLogoRaw, ['http://', 'https://']) ? $appLogoRaw : asset(ltrim($appLogoRaw, '/'));
        $tournamentLogo = $tournament->settings?->logo_url;
    @endphp
    <div style="background: {{ $primaryColor }}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <div style="margin: 0 auto 15px;">
            <img src="{{ $appLogo }}" alt="{{ config('app.name') }}" style="height: 56px; max-width: 160px; object-fit: contain; background: #ffffff; border-radius: 8px; padding: 8px; vertical-align: middle;">
            @if($tournamentLogo)
                <img src="{{ $tournamentLogo }}" alt="{{ $tournamentName }}" style="height: 56px; width: 56px; object-fit: contain; background: #ffffff; border-radius: 50%; padding: 6px; vertical-align: middle; margin-left: 12px;">
            @endif
        </div>
        <h1 style="color: #ffffff; margin: 0; font-size: 22px;">Please review your registration</h1>
        <p style="color: rgba(255,255,255,0.85); margin: 10px 0 0 0;">{{ $tournamentName }}</p>
    </div>

    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 15px 0;">Hi {{ $applicantName }},</p>
        <p style="margin: 0 0 20px 0;">We reviewed your registration for <strong>{{ $tournamentName }}</strong>. The following details could not be verified and need your attention. Please reply to this email with the correct information (or updated documents).</p>

        @if(!empty($fields))
        <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
            <h3 style="margin: 0 0 12px 0; color: #495057; font-size: 15px;">Details to review</h3>
            <ul style="margin: 0; padding-left: 20px; color: #495057;">
                @foreach($fields as $field)
                    <li style="padding: 3px 0;">{{ $field }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(!empty($note))
        <div style="background: #eef2ff; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <p style="margin: 0; color: #3730a3; font-size: 14px;"><strong>Note from the organizer:</strong><br>{!! nl2br(e($note)) !!}</p>
        </div>
        @endif

        <p style="margin: 0; color: #6c757d; font-size: 14px;">Thank you for helping us keep your registration accurate.</p>
    </div>

    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">This message was sent by {{ config('app.name') }} on behalf of {{ $tournamentName }}.</p>
    </div>
</body>
</html>
