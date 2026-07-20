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
        $tournamentLogo = $tournament->settings?->logo_url ?: $tournament->logo_url;
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
        <p style="margin: 0 0 20px 0;">Here's the current review status of your registration for <strong>{{ $tournamentName }}</strong>, section by section.</p>

        @if(!empty($acceptedGroups))
        <div style="background: #ecfdf5; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; border-left: 4px solid #10b981;">
            <h3 style="margin: 0 0 8px 0; color: #065f46; font-size: 15px;">✓ Accepted</h3>
            <ul style="margin: 0; padding-left: 20px; color: #065f46;">
                @foreach($acceptedGroups as $group)
                    <li style="padding: 3px 0;">{{ $group }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if(!empty($pendingGroups))
        <div style="background: #fffbeb; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; border-left: 4px solid #f59e0b;">
            <h3 style="margin: 0 0 8px 0; color: #92400e; font-size: 15px;">⏳ Still pending review</h3>
            @foreach($pendingGroups as $group)
                <p style="margin: 8px 0 2px; color: #92400e; font-weight: 600; font-size: 14px;">{{ $group['section'] }}</p>
                <ul style="margin: 0 0 6px; padding-left: 20px; color: #92400e;">
                    @foreach($group['fields'] as $field)
                        <li style="padding: 2px 0;">{{ $field }}</li>
                    @endforeach
                </ul>
            @endforeach
            <p style="margin: 10px 0 0; color: #92400e; font-size: 13px;">Please use the credentials below to log in and update the fields marked as pending.</p>
        </div>
        @else
        <div style="background: #ecfdf5; border-radius: 8px; padding: 14px 20px; margin-bottom: 16px;">
            <p style="margin: 0; color: #065f46; font-size: 14px;">🎉 All sections of your registration have been verified. No action needed.</p>
        </div>
        @endif

        @if(!empty($note))
        <div style="background: #eef2ff; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <p style="margin: 0; color: #3730a3; font-size: 14px;"><strong>Note from the organizer:</strong><br>{!! nl2br(e($note)) !!}</p>
        </div>
        @endif

        @if($tempPassword)
        <div style="background: #f0fdf4; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; border-left: 4px solid #22c55e;">
            <h3 style="margin: 0 0 10px 0; color: #166534; font-size: 15px;">🔑 Your Login Details</h3>
            <p style="margin: 0 0 6px 0; color: #166534; font-size: 14px;">You can log in to review and update your registration:</p>
            <table style="margin: 8px 0; font-size: 14px; color: #166534;">
                <tr><td style="padding: 3px 12px 3px 0; font-weight: 600;">Login URL:</td><td><a href="{{ $loginUrl }}" style="color: #1a56db;">{{ $loginUrl }}</a></td></tr>
                <tr><td style="padding: 3px 12px 3px 0; font-weight: 600;">Email:</td><td>{{ $loginEmail }}</td></tr>
                <tr><td style="padding: 3px 12px 3px 0; font-weight: 600;">Temp Password:</td><td style="font-family: monospace; background: #dcfce7; padding: 2px 8px; border-radius: 4px;">{{ $tempPassword }}</td></tr>
            </table>
            <p style="margin: 8px 0 0 0; color: #f59e0b; font-size: 12px;">⚠️ For security, please change your password after logging in.</p>
        </div>
        @endif

        <p style="margin: 0; color: #6c757d; font-size: 14px;">Thank you for helping us keep your registration accurate.</p>
    </div>

    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">This message was sent by {{ config('app.name') }} on behalf of {{ $tournamentName }}.</p>
    </div>
</body>
</html>
