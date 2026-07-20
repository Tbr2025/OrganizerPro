<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile update to review</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    @php
        $primaryColor = $tournament->settings?->primary_color ?? '#1a56db';
        $appLogoRaw = config('settings.site_logo_lite') ?: 'images/logo/lara-dashboard.png';
        $appLogo = \Illuminate\Support\Str::startsWith($appLogoRaw, ['http://', 'https://']) ? $appLogoRaw : asset(ltrim($appLogoRaw, '/'));
        $tournamentLogo = $tournament->settings?->logo_url ?: $tournament->logo_url;
    @endphp
    <div style="background: {{ $primaryColor }}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <div style="margin: 0 auto 15px;">
            <img src="{{ $appLogo }}" alt="{{ config('app.name') }}" style="height: 52px; max-width: 150px; object-fit: contain; background: #ffffff; border-radius: 8px; padding: 6px; vertical-align: middle;">
            @if($tournamentLogo)
                <img src="{{ $tournamentLogo }}" alt="{{ $tournamentName }}" style="height: 52px; width: 52px; object-fit: contain; background: #ffffff; border-radius: 50%; padding: 5px; vertical-align: middle; margin-left: 10px;">
            @endif
        </div>
        <h1 style="color: #ffffff; margin: 0; font-size: 22px;">Profile update to review</h1>
        <p style="color: rgba(255,255,255,0.85); margin: 10px 0 0 0;">{{ $tournamentName }}</p>
    </div>

    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 16px 0;"><strong>{{ $playerName }}</strong> submitted the following changes to their registration. They are <strong>pending your approval</strong> and won't reflect until you approve them.</p>

        <div style="background: white; border-radius: 8px; padding: 6px 16px; margin-bottom: 20px; border-left: 4px solid {{ $primaryColor }};">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tr style="text-align: left; color: #6c757d; font-size: 11px; text-transform: uppercase;">
                    <th style="padding: 8px 8px 8px 0;">Field</th>
                    <th style="padding: 8px;">Current</th>
                    <th style="padding: 8px;">Requested</th>
                </tr>
                @foreach($changes as $c)
                <tr style="border-top: 1px solid #eee;">
                    <td style="padding: 10px 8px 10px 0; font-weight: 600; color: #495057;">{{ $c['label'] }}</td>
                    <td style="padding: 10px 8px; color: #6c757d;">{{ $c['old'] === '' ? '—' : $c['old'] }}</td>
                    <td style="padding: 10px 8px; font-weight: 600; color: #b45309;">{{ $c['new'] === '' ? '—' : $c['new'] }}</td>
                </tr>
                @endforeach
            </table>
        </div>

        <div style="text-align: center;">
            <a href="{{ $reviewUrl }}" style="display: inline-block; background: {{ $primaryColor }}; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600;">Review &amp; Approve</a>
        </div>
    </div>

    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">This is an automated notification from {{ config('app.name') }}.</p>
    </div>
</body>
</html>
