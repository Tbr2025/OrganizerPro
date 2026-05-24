<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You've Been Selected!</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    @php
        $tournament = $auction->tournament;
        $primaryColor = $tournament?->settings?->primary_color ?? '#1a56db';
        $secondaryColor = $tournament?->settings?->secondary_color ?? '#ffffff';
    @endphp
    <div style="background: {{ $primaryColor }}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <img src="{{ $tournament?->settings?->logo_url ?? url('/images/logo/logo.png') }}" alt="{{ $tournament?->name ?? config('app.name') }}" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; display: block; object-fit: contain; background: white; padding: 8px;">
        <h1 style="color: {{ $secondaryColor }}; margin: 0; font-size: 24px;">You've Been Selected!</h1>
    </div>

    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 20px 0; font-size: 16px;">
            Dear <strong>{{ $player->name }}</strong>,
        </p>

        <p style="margin: 0 0 20px 0;">
            Congratulations! You have been selected by <strong>{{ $team->name }}</strong> in the <strong>{{ $auction->name }}</strong> auction.
        </p>

        <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid {{ $primaryColor }};">
            <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px;">Auction Details</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; color: #6c757d; width: 40%;">Team:</td>
                    <td style="padding: 8px 0; font-weight: 600;">{{ $team->name }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;">Auction:</td>
                    <td style="padding: 8px 0;">{{ $auction->name }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;">Sale Amount:</td>
                    <td style="padding: 8px 0; font-weight: 600; color: {{ $primaryColor }};">{{ number_format($finalPrice) }}</td>
                </tr>
                @if($tournament)
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;">Tournament:</td>
                    <td style="padding: 8px 0;">{{ $tournament->name }}</td>
                </tr>
                @endif
            </table>
        </div>

        <div style="background: #d4edda; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <p style="margin: 0; color: #155724; font-size: 14px;">
                Welcome to <strong>{{ $team->name }}</strong>! Get ready to give your best performance. Stay tuned for further updates and match schedules.
            </p>
        </div>

        @if($tournament?->slug)
        <div style="text-align: center;">
            <a href="{{ route('public.tournament.show', $tournament->slug) }}"
               style="display: inline-block; background: {{ $primaryColor }}; color: {{ $secondaryColor }}; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600;">
                View Tournament
            </a>
        </div>
        @endif
    </div>

    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">Congratulations on being selected!</p>
        <p style="margin: 5px 0 0 0;">{{ config('app.name') }}</p>
    </div>
</body>
</html>
