<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $team->name }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f7;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 25px;
            background-color: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #eeeeee;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 24px;
        }
        .team-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            margin-top: 10px;
        }
        .content p {
            margin: 1.2em 0;
            font-size: 16px;
        }
        .content strong {
            color: #0056b3;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 16px;
        }
        .info-box p {
            margin: 5px 0;
            font-size: 14px;
        }
        .status-pending {
            display: inline-block;
            background-color: #ffc107;
            color: #000;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9em;
            color: #777;
            border-top: 1px solid #eeeeee;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to the Team!</h1>
            <span class="team-badge">{{ $team->name }}</span>
        </div>

        <div class="content">
            <p>Hi <strong>{{ $player->name }}</strong>,</p>

            <p>
                Great news! You have been added to <strong>{{ $team->name }}</strong>
                @if($team->tournament)
                    for the <strong>{{ $team->tournament->name }}</strong> tournament.
                @endif
            </p>

            <div class="info-box">
                <h3>Your Registration Details</h3>
                <p><strong>Team:</strong> {{ $team->name }}</p>
                @if($team->tournament)
                    <p><strong>Tournament:</strong> {{ $team->tournament->name }}</p>
                @endif
                @if($player->jersey_number)
                    <p><strong>Jersey Number:</strong> #{{ $player->jersey_number }}</p>
                @endif
                <p><strong>Status:</strong> <span class="status-pending">Pending Verification</span></p>
            </div>

            <p>
                Your registration is currently pending verification by the team manager. Once verified, you will be officially part of the team roster.
            </p>

            <p>
                If you have any questions, please contact your team manager.
            </p>
        </div>

        <div class="footer">
            <p>Best regards,</p>
            <p>The {{ config('app.name', 'OrganizerPro') }} Team</p>
        </div>
    </div>
</body>
</html>
