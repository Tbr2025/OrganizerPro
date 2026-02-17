<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Approved</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <div style="background: white; width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#28a745" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <h1 style="color: white; margin: 0; font-size: 24px;">Registration Approved!</h1>
    </div>

    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        @if($registration->type === 'player')
            <p style="margin: 0 0 20px 0; font-size: 16px;">
                Dear <strong>{{ $registration->player->name ?? 'Player' }}</strong>,
            </p>
            <p style="margin: 0 0 20px 0;">
                Great news! Your registration for <strong>{{ $tournament->name }}</strong> has been approved.
            </p>
        @else
            <p style="margin: 0 0 20px 0; font-size: 16px;">
                Dear <strong>{{ $registration->captain_name }}</strong>,
            </p>
            <p style="margin: 0 0 20px 0;">
                Great news! Your team <strong>{{ $registration->team_name }}</strong> has been approved for <strong>{{ $tournament->name }}</strong>.
            </p>
        @endif

        <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #28a745;">
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

        <div style="background: #d4edda; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <p style="margin: 0; color: #155724; font-size: 14px;">
                You are now officially part of this tournament. Stay tuned for further updates and match schedules!
            </p>
        </div>

        <div style="text-align: center;">
            <a href="{{ route('public.tournament.show', $tournament->slug) }}"
               style="display: inline-block; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600;">
                View Tournament
            </a>
        </div>
    </div>

    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">Thank you for registering with {{ config('app.name') }}</p>
        <p style="margin: 5px 0 0 0;">Good luck!</p>
    </div>
</body>
</html>
