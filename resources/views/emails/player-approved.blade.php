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
    @endphp
    <div style="background: {{ $primaryColor }}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <img src="{{ $team->tournament?->settings?->logo_url ?? url('/images/logo/logo.png') }}" alt="{{ $team->tournament?->name ?? config('app.name') }}" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; display: block; object-fit: contain; background: white; padding: 8px;">
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
                @if($player->jersey_number)
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;">Jersey Number:</td>
                    <td style="padding: 8px 0;">#{{ $player->jersey_number }}</td>
                </tr>
                @endif
            </table>
        </div>

        <div style="background: #d4edda; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <p style="margin: 0; color: #155724; font-size: 14px;">
                You are now officially part of the team roster. Stay tuned for further updates and match schedules!
            </p>
        </div>

        @if($team->tournament?->slug)
        <div style="text-align: center;">
            <a href="{{ route('public.tournament.show', $team->tournament->slug) }}"
               style="display: inline-block; background: {{ $primaryColor }}; color: {{ $secondaryColor }}; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600;">
                View Tournament
            </a>
        </div>
        @endif
    </div>

    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">Thank you for being part of {{ $team->name }}!</p>
        <p style="margin: 5px 0 0 0;">{{ config('app.name') }}</p>
    </div>
</body>
</html>
