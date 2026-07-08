<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your login details</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    @php
        $primaryColor = $tournament->settings?->primary_color ?? '#1a56db';
        $appLogoRaw = config('settings.site_logo_lite') ?: 'images/logo/lara-dashboard.png';
        $appLogo = \Illuminate\Support\Str::startsWith($appLogoRaw, ['http://', 'https://']) ? $appLogoRaw : asset(ltrim($appLogoRaw, '/'));
        $tournamentLogo = $tournament?->settings?->logo_url;
        $loginUrl = url('/login');
    @endphp
    <div style="background: {{ $primaryColor }}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <div style="margin: 0 auto 15px;">
            <img src="{{ $appLogo }}" alt="{{ config('app.name') }}" style="height: 56px; max-width: 160px; object-fit: contain; background: #ffffff; border-radius: 8px; padding: 8px; vertical-align: middle;">
            @if($tournamentLogo)
                <img src="{{ $tournamentLogo }}" alt="{{ $tournament->name }}" style="height: 56px; width: 56px; object-fit: contain; background: #ffffff; border-radius: 50%; padding: 6px; vertical-align: middle; margin-left: 12px;">
            @endif
        </div>
        <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Your account is ready</h1>
        @if($tournament)<p style="color: rgba(255,255,255,0.85); margin: 10px 0 0 0;">{{ $tournament->name }}</p>@endif
    </div>

    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 20px 0; font-size: 16px;">Dear <strong>{{ $user->name }}</strong>,</p>
        <p style="margin: 0 0 20px 0;">Thanks for registering. We've created an account for you so you can log in, track your application, and update your details.</p>

        <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid {{ $primaryColor }};">
            <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px;">Your login details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; color: #6c757d; width: 40%;">Login URL:</td>
                    <td style="padding: 8px 0; font-weight: 600;"><a href="{{ $loginUrl }}" style="color: {{ $primaryColor }};">{{ $loginUrl }}</a></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;">Email:</td>
                    <td style="padding: 8px 0; font-weight: 600;">{{ $user->email }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;">Password:</td>
                    <td style="padding: 8px 0; font-weight: 600; font-family: monospace; font-size: 16px; letter-spacing: 1px;">{{ $password }}</td>
                </tr>
            </table>
        </div>

        <div style="background: #fff3cd; border-radius: 8px; padding: 15px; margin-bottom: 20px; border-left: 4px solid #f0ad4e;">
            <p style="margin: 0; color: #856404; font-size: 14px;">For your security, please log in and change your password from your profile.</p>
        </div>

        <div style="text-align: center;">
            <a href="{{ $loginUrl }}" style="display: inline-block; background: {{ $primaryColor }}; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600;">Log in</a>
        </div>
    </div>

    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">This is an automated message from {{ config('app.name') }}.</p>
    </div>
</body>
</html>
