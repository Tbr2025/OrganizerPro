<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Player Join Request</title>
    @php
        $primaryColor = $team->tournament?->settings?->primary_color ?? '#1a56db';
        $secondaryColor = $team->tournament?->settings?->secondary_color ?? '#ffffff';
    @endphp
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f7; margin: 0; padding: 0;">
    <div style="max-width: 600px; margin: 20px auto; padding: 0;">

        <div style="background: {{ $primaryColor }}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
            <img src="{{ $team->tournament?->settings?->logo_url ?? url('/images/logo/logo.png') }}" alt="{{ $team->tournament?->name ?? config('app.name') }}" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; display: block; object-fit: contain; background: white; padding: 8px;">
            <h1 style="color: {{ $secondaryColor }}; margin: 0; font-size: 22px;">New Player Join Request</h1>
            <p style="color: {{ $secondaryColor }}; margin: 10px 0 0 0; opacity: 0.9;">{{ $team->name }}</p>
        </div>

        <div style="background: #ffffff; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
            <p style="margin: 0 0 20px 0; font-size: 16px;">
                A new player has requested to join your team.
            </p>

            <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid {{ $primaryColor }};">
                <h3 style="margin: 0 0 15px 0; color: #2c3e50; font-size: 16px;">Player Details</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d; width: 40%;">Name:</td>
                        <td style="padding: 8px 0; font-weight: 600;">{{ $player->name }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d;">Email:</td>
                        <td style="padding: 8px 0;">{{ $player->email }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d;">Mobile:</td>
                        <td style="padding: 8px 0;">{{ $player->mobile_number_full }}</td>
                    </tr>
                    @if($player->playerType)
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d;">Player Type:</td>
                        <td style="padding: 8px 0;">{{ $player->playerType->name }}</td>
                    </tr>
                    @endif
                    @if($player->jersey_name)
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d;">Jersey Name:</td>
                        <td style="padding: 8px 0;">{{ $player->jersey_name }}</td>
                    </tr>
                    @endif
                    @if($player->jersey_number)
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d;">Jersey Number:</td>
                        <td style="padding: 8px 0;">#{{ $player->jersey_number }}</td>
                    </tr>
                    @endif
                </table>
            </div>

            <div style="background: #fff3cd; border-radius: 8px; padding: 15px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                <p style="margin: 0; color: #856404; font-size: 14px;">
                    <strong>Action Required:</strong> Please review and approve or reject this player from your Team Manager Dashboard.
                </p>
            </div>

            <div style="text-align: center;">
                <a href="{{ $dashboardUrl }}"
                   style="display: inline-block; background: {{ $primaryColor }}; color: {{ $secondaryColor }}; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600;">
                    Open Team Dashboard
                </a>
            </div>
        </div>

        <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px; border-radius: 0 0 10px 10px;">
            <p style="margin: 0;">This is an automated notification from {{ config('app.name', 'Sportzley') }}</p>
        </div>
    </div>
</body>
</html>
