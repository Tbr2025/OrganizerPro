
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name', 'OrganizerPro') }}</title>
    <style>
        /* Basic styling for email clients */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
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
        .content p {
            margin: 1.2em 0;
            font-size: 16px;
        }
        .content strong {
            color: #0056b3;
        }
        .cta-button {
            display: block;
            width: fit-content;
            margin: 25px auto;
            padding: 12px 25px;
            background-color: #007bff;
            color: #ffffff !important; /* Important for email client compatibility */
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9em;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome Aboard!</h1>
        </div>

        <div class="content">
            {{-- The $player variable is passed from the Mailable class --}}
            <p>Hi <strong>{{ $player->name }}</strong>,</p>

            <p>
                A warm welcome to <strong>{{ config('app.name', 'OrganizerPro') }}</strong>! We are thrilled to have you join our community.
            </p>

            <p>
                Your journey to track your performance, join events, and showcase your skills starts now. To get the most out of the platform, we recommend completing your profile.
            </p>
            
            {{-- This button should link to the user's profile page or dashboard --}}
            <a href="#" class="cta-button">Complete Your Profile</a>
            
            <p>
                If you have any questions, feel free to reply to this email. We're happy to help!
            </p>
        </div>

        <div class="footer">
            <p>Best regards,</p>
            <p>The {{ config('app.name', 'OrganizerPro') }} Team</p>
        </div>
    </div>
</body>
</html>