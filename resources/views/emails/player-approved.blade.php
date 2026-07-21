<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Approved</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    @php
        $primaryColor = $team->tournament?->settings?->primary_color ?? '#1a56db';
        $secondaryColor = $team->tournament?->settings?->secondary_color ?? '#ffffff';
        $appLogoRaw = config('settings.site_logo_lite') ?: 'images/logo/lara-dashboard.png';
        $appLogo = \Illuminate\Support\Str::startsWith($appLogoRaw, ['http://', 'https://']) ? $appLogoRaw : asset(ltrim($appLogoRaw, '/'));
        $tournamentLogo = $team->tournament?->settings?->logo_url;
    @endphp
    <div style="background: {{ $primaryColor }}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <div style="margin: 0 auto 15px;">
            <img src="{{ $appLogo }}" alt="{{ config('app.name') }}" style="height: 56px; max-width: 160px; object-fit: contain; background: #ffffff; border-radius: 8px; padding: 8px; vertical-align: middle;">
            @if($tournamentLogo)
                <img src="{{ $tournamentLogo }}" alt="{{ $team->tournament?->name }}" style="height: 56px; width: 56px; object-fit: contain; background: #ffffff; border-radius: 50%; padding: 6px; vertical-align: middle; margin-left: 12px;">
            @endif
        </div>
        <h1 style="color: {{ $secondaryColor }}; margin: 0; font-size: 24px;">You're Approved!</h1>
    </div>

    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 20px 0; font-size: 16px;">
            Dear <strong>{{ $player->name }}</strong>,
        </p>

        <p style="margin: 0 0 20px 0;">
            Great news! You have been approved and verified as a member of <strong>{{ $team->name }}</strong>
            @if($team->tournament)
                for the <strong>{{ $team->tournament->name }}</strong> tournament.
            @endif
        </p>

        <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid {{ $primaryColor }};">
            <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px;">Your Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; color: #6c757d; width: 40%;">Team:</td>
                    <td style="padding: 8px 0; font-weight: 600;">{{ $team->name }}</td>
                </tr>
                @if($team->tournament)
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;">Tournament:</td>
                    <td style="padding: 8px 0;">{{ $team->tournament->name }}</td>
                </tr>
                @endif
                @if($team->tournament?->start_date)
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;">Start Date:</td>
                    <td style="padding: 8px 0;">{{ \Carbon\Carbon::parse($team->tournament->start_date)->format('d M Y') }}</td>
                </tr>
                @endif
                @if($team->tournament?->location)
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;">Location:</td>
                    <td style="padding: 8px 0;">{{ $team->tournament->location }}</td>
                </tr>
                @endif
            </table>
        </div>

        @if(!empty($welcomeCardBase64))
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="{{ $welcomeCardBase64 }}" alt="Welcome Card" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
        </div>
        @endif

        <div style="background: #d4edda; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <p style="margin: 0; color: #155724; font-size: 14px;">
                You are now officially part of the team roster. Stay tuned for further updates and match schedules!
            </p>
        </div>

        @if($team->tournament?->slug)
        <div style="text-align: center; margin-bottom: 20px;">
            <a href="{{ route('public.tournament.show', $team->tournament->slug) }}"
               style="display: inline-block; background: {{ $primaryColor }}; color: {{ $secondaryColor }}; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600;">
                View Tournament
            </a>
        </div>
        @endif

        @php
            $tSettings = $team->tournament?->settings;
            $contactParts = [];
            if ($tSettings?->contact_email) $contactParts[] = '<a href="mailto:' . e($tSettings->contact_email) . '" style="color:' . e($primaryColor) . ';font-weight:600;text-decoration:none;">' . e($tSettings->contact_email) . '</a>';
            if ($tSettings?->contact_phone) $contactParts[] = '<a href="tel:' . e($tSettings->contact_phone) . '" style="color:' . e($primaryColor) . ';font-weight:600;text-decoration:none;">' . e($tSettings->contact_phone) . '</a>';
            if ($tSettings?->whatsapp_contact) $contactParts[] = '<a href="https://wa.me/' . preg_replace('/[^0-9]/', '', $tSettings->whatsapp_contact) . '" style="color:#25d366;font-weight:600;text-decoration:none;">WhatsApp</a>';
        @endphp
        @if(count($contactParts))
        <p style="margin: 0; font-size: 14px; color: #555;">Contact us: {!! implode(' &nbsp;|&nbsp; ', $contactParts) !!}</p>
        @endif
    </div>

    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">Thank you for being part of {{ $team->name }}!</p>
        <p style="margin: 5px 0 0 0;">{{ config('app.name') }}</p>
    </div>
</body>
</html>
