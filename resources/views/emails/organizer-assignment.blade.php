<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    @php
        $primaryColor = config('settings.primary_color') ?? '#1a56db';
        $appLogoRaw = config('settings.site_logo_lite') ?: 'images/logo/lara-dashboard.png';
        $appLogo = \Illuminate\Support\Str::startsWith($appLogoRaw, ['http://', 'https://']) ? $appLogoRaw : asset(ltrim($appLogoRaw, '/'));
        $brand = config('settings.app_name') ?: config('app.name');
    @endphp
    <div style="background: {{ $primaryColor }}; padding: 28px; text-align: center; border-radius: 10px 10px 0 0;">
        <img src="{{ $appLogo }}" alt="{{ $brand }}" style="height: 50px; max-width: 160px; object-fit: contain; background: #fff; border-radius: 8px; padding: 6px;">
        <h1 style="color: #fff; margin: 12px 0 0; font-size: 22px;">
            @if($mode === 'removed') Organizer access updated
            @elseif($mode === 'updated') Your assignments were updated
            @else You're now an organizer @endif
        </h1>
    </div>

    <div style="background: #f8f9fa; padding: 28px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 16px;">Hi <strong>{{ $user->name }}</strong>,</p>

        @if($mode === 'removed')
            <p style="margin: 0 0 16px;">Your organizer assignments on {{ $brand }} have been updated. Your current access is shown below.</p>
        @elseif($mode === 'updated')
            <p style="margin: 0 0 16px;">An administrator updated what you can manage on {{ $brand }}. Here's your current access:</p>
        @else
            <p style="margin: 0 0 16px;">An administrator has assigned you as an organizer on {{ $brand }}. You can now log in and manage the items below.</p>
        @endif

        @if($password)
        <div style="background: white; border-radius: 8px; padding: 16px 20px; margin-bottom: 18px; border-left: 4px solid {{ $primaryColor }};">
            <h3 style="margin: 0 0 10px; font-size: 15px; color: #495057;">Your login</h3>
            <table style="width:100%; border-collapse: collapse; font-size:14px;">
                <tr><td style="color:#6c757d; padding:4px 0; width:35%;">URL</td><td><a href="{{ $loginUrl }}" style="color: {{ $primaryColor }};">{{ $loginUrl }}</a></td></tr>
                <tr><td style="color:#6c757d; padding:4px 0;">Email</td><td style="font-weight:600;">{{ $user->email }}</td></tr>
                <tr><td style="color:#6c757d; padding:4px 0;">Password</td><td style="font-weight:600; font-family:monospace; letter-spacing:1px;">{{ $password }}</td></tr>
            </table>
            <p style="margin: 10px 0 0; font-size:12px; color:#856404;">Please change your password after logging in.</p>
        </div>
        @endif

        @php $hasAny = count($tournaments) || count($teams) || count($matches); @endphp
        @if($hasAny)
        <div style="background: white; border-radius: 8px; padding: 16px 20px; margin-bottom: 18px; border-left: 4px solid #10b981;">
            <h3 style="margin: 0 0 10px; font-size: 15px; color: #495057;">You can manage</h3>
            @if(count($tournaments))
                <p style="margin: 8px 0 2px; font-weight:600; color:#374151;">Tournaments</p>
                <ul style="margin:0; padding-left:20px; color:#374151;">@foreach($tournaments as $t)<li>{{ $t }}</li>@endforeach</ul>
            @endif
            @if(count($teams))
                <p style="margin: 8px 0 2px; font-weight:600; color:#374151;">Teams</p>
                <ul style="margin:0; padding-left:20px; color:#374151;">@foreach($teams as $t)<li>{{ $t }}</li>@endforeach</ul>
            @endif
            @if(count($matches))
                <p style="margin: 8px 0 2px; font-weight:600; color:#374151;">Matches</p>
                <ul style="margin:0; padding-left:20px; color:#374151;">@foreach($matches as $m)<li>{{ $m }}</li>@endforeach</ul>
            @endif
        </div>
        @else
        <div style="background: #fff3cd; border-radius: 8px; padding: 14px 20px; margin-bottom: 18px;">
            <p style="margin:0; color:#856404; font-size:14px;">You currently have no items assigned.</p>
        </div>
        @endif

        <div style="text-align:center;">
            <a href="{{ $loginUrl }}" style="display:inline-block; background: {{ $primaryColor }}; color:#fff; padding:12px 30px; text-decoration:none; border-radius:6px; font-weight:600;">Log in</a>
        </div>
    </div>

    <div style="text-align:center; padding:20px; color:#6c757d; font-size:12px;">
        <p style="margin:0;">Automated message from {{ $brand }}.</p>
    </div>
</body>
</html>
