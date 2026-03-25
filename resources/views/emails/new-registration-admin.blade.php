<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Registration</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    @php
        $primaryColor = $tournament->settings?->primary_color ?? '#1a56db';
        $secondaryColor = $tournament->settings?->secondary_color ?? '#ffffff';
    @endphp
    <div style="background: {{ $primaryColor }}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <img src="{{ $tournament->settings?->logo_url ?? url('/images/logo/logo.png') }}" alt="{{ $tournament->name }}" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; display: block; object-fit: contain; background: white; padding: 8px;">
        <h1 style="color: {{ $secondaryColor }}; margin: 0; font-size: 24px;">New {{ ucfirst($registrationType) }} Registration</h1>
        <p style="color: {{ $secondaryColor }}; margin: 10px 0 0 0;">{{ $tournament->name }}</p>
    </div>

    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 20px 0;">A new {{ $registrationType }} has registered for your tournament.</p>

        @if($registrationType === 'player')
            <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px; border-bottom: 2px solid {{ $primaryColor }}; padding-bottom: 10px;">Player Details</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d; width: 40%;">Name:</td>
                        <td style="padding: 8px 0; font-weight: 600;">{{ $registration->player->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d;">Email:</td>
                        <td style="padding: 8px 0;">{{ $registration->player->email ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d;">Phone:</td>
                        <td style="padding: 8px 0;">{{ $registration->player->mobile_number_full ?? 'N/A' }}</td>
                    </tr>
                </table>
            </div>
        @else
            <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px; border-bottom: 2px solid {{ $primaryColor }}; padding-bottom: 10px;">Team Details</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d; width: 40%;">Team Name:</td>
                        <td style="padding: 8px 0; font-weight: 600;">{{ $registration->team_name }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d;">Manager Name:</td>
                        <td style="padding: 8px 0;">{{ $registration->captain_name }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d;">Manager Email:</td>
                        <td style="padding: 8px 0;">{{ $registration->captain_email }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d;">Manager Phone:</td>
                        <td style="padding: 8px 0;">{{ $registration->captain_phone }}</td>
                    </tr>
                </table>
            </div>
        @endif

        <div style="background: #fff3cd; border-radius: 8px; padding: 15px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
            <p style="margin: 0; color: #856404; font-size: 14px;">
                <strong>Action Required:</strong> Please review and approve/reject this registration.
            </p>
        </div>

        <div style="text-align: center;">
            <a href="{{ route('admin.tournaments.registrations.index', $tournament) }}"
               style="display: inline-block; background: {{ $primaryColor }}; color: {{ $secondaryColor }}; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600;">
                View Registrations
            </a>
        </div>
    </div>

    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">This is an automated notification from {{ config('app.name') }}</p>
    </div>
</body>
</html>
