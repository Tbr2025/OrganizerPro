<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Received</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    @php
        $primaryColor = $tournament->settings?->primary_color ?? '#1a56db';
        $appLogoRaw = config('settings.site_logo_lite') ?: 'images/logo/lara-dashboard.png';
        $appLogo = \Illuminate\Support\Str::startsWith($appLogoRaw, ['http://', 'https://']) ? $appLogoRaw : asset(ltrim($appLogoRaw, '/'));
        $tournamentLogo = $tournament->settings?->logo_url;
    @endphp
    <div style="background: {{ $primaryColor }}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <div style="margin: 0 auto 15px;">
            <img src="{{ $appLogo }}" alt="{{ config('app.name') }}" style="height: 56px; max-width: 160px; object-fit: contain; background: #ffffff; border-radius: 8px; padding: 8px; vertical-align: middle;">
            @if($tournamentLogo)
                <img src="{{ $tournamentLogo }}" alt="{{ $tournament->name }}" style="height: 56px; width: 56px; object-fit: contain; background: #ffffff; border-radius: 50%; padding: 6px; vertical-align: middle; margin-left: 12px;">
            @endif
        </div>
        <h1 style="color: #ffffff; margin: 0; font-size: 24px;">Application Received 🎉</h1>
    </div>

    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 20px 0; font-size: 16px;">
            Dear <strong>{{ $applicantName }}</strong>,
        </p>
        <p style="margin: 0 0 20px 0;">
            Congratulations — your {{ $registration->type === 'team' ? 'team registration' : 'application' }}
            for <strong>{{ $tournament->name }}</strong> has been submitted successfully.
        </p>

        <div style="background: #fff3cd; border-radius: 8px; padding: 15px; margin-bottom: 20px; border-left: 4px solid #f0ad4e;">
            <p style="margin: 0; color: #856404; font-size: 15px;">
                <strong>You're in the queue.</strong> Your application is now <strong>under review</strong> by the
                organizers. We'll email you again as soon as it's approved.
            </p>
        </div>

        <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid {{ $primaryColor }};">
            <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px;">Tournament Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; color: #6c757d; width: 40%;">Tournament:</td>
                    <td style="padding: 8px 0; font-weight: 600;">{{ $tournament->name }}</td>
                </tr>
                @if($tournament->start_date)
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;">Start Date:</td>
                    <td style="padding: 8px 0;">{{ \Carbon\Carbon::parse($tournament->start_date)->format('d M Y') }}</td>
                </tr>
                @endif
                @if($tournament->location)
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;">Location:</td>
                    <td style="padding: 8px 0;">{{ $tournament->location }}</td>
                </tr>
                @endif
            </table>
        </div>
        @php
            $settings = $tournament->settings;
            $contactParts = [];
            if ($settings?->contact_email) $contactParts[] = '<a href="mailto:' . e($settings->contact_email) . '" style="color:' . e($primaryColor) . ';font-weight:600;text-decoration:none;">' . e($settings->contact_email) . '</a>';
            if ($settings?->contact_phone) $contactParts[] = '<a href="tel:' . e($settings->contact_phone) . '" style="color:' . e($primaryColor) . ';font-weight:600;text-decoration:none;">' . e($settings->contact_phone) . '</a>';
            if ($settings?->whatsapp_contact) $contactParts[] = '<a href="https://wa.me/' . preg_replace('/[^0-9]/', '', $settings->whatsapp_contact) . '" style="color:#25d366;font-weight:600;text-decoration:none;">WhatsApp</a>';
        @endphp
        @if(count($contactParts))
        <p style="margin: 0; font-size: 14px; color: #555;">Contact us: {!! implode(' &nbsp;|&nbsp; ', $contactParts) !!}</p>
        @endif
    </div>

    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">Thank you for registering with {{ config('app.name') }}</p>
        <p style="margin: 5px 0 0 0;">We'll be in touch soon!</p>
    </div>
</body>
</html>
