<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $roleName }} Account</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    @php
        $primaryColor = $tournament->settings?->primary_color ?? '#1a56db';
        $secondaryColor = $tournament->settings?->secondary_color ?? '#ffffff';
    @endphp
    <div style="background: {{ $primaryColor }}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <img src="{{ $tournament->settings?->logo_url ?? url('/images/logo/logo.png') }}" alt="{{ $tournament->name }}" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; display: block; object-fit: contain; background: white; padding: 8px;">
        @if($isNewUser)
            <h1 style="color: {{ $secondaryColor }}; margin: 0; font-size: 24px;">{{ $roleName }} Account Created</h1>
        @else
            <h1 style="color: {{ $secondaryColor }}; margin: 0; font-size: 24px;">You've Been Added as {{ $roleName }}</h1>
        @endif
    </div>

    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        <p style="margin: 0 0 20px 0; font-size: 16px;">
            Dear <strong>{{ $user->name }}</strong>,
        </p>

        @if($isNewUser)
            <p style="margin: 0 0 20px 0;">
                Your team <strong>{{ $team->name }}</strong> has been approved for <strong>{{ $tournament->name }}</strong>.
                A {{ strtolower($roleName) }} account has been created for you to manage your team.
            </p>

            <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid {{ $primaryColor }};">
                <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px;">Your Login Credentials</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d; width: 40%;">Login URL:</td>
                        <td style="padding: 8px 0; font-weight: 600;">
                            <a href="{{ url('/admin/login') }}" style="color: {{ $primaryColor }};">{{ url('/admin/login') }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d;">Email:</td>
                        <td style="padding: 8px 0; font-weight: 600;">{{ $user->email }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d;">Password:</td>
                        <td style="padding: 8px 0; font-weight: 600; font-family: monospace; font-size: 16px; letter-spacing: 1px;">{{ $password }}</td>
                    </tr>
                </table>
            </div>

            <div style="background: #fff3cd; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <p style="margin: 0; color: #856404; font-size: 14px;">
                    Please change your password after your first login for security.
                </p>
            </div>
        @else
            <p style="margin: 0 0 20px 0;">
                Your team <strong>{{ $team->name }}</strong> has been approved for <strong>{{ $tournament->name }}</strong>.
                You have been assigned as <strong>{{ $roleName }}</strong> for this team.
            </p>

            <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; border-left: 4px solid {{ $primaryColor }};">
                <h3 style="margin: 0 0 15px 0; color: #495057; font-size: 16px;">Login Details</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d; width: 40%;">Login URL:</td>
                        <td style="padding: 8px 0; font-weight: 600;">
                            <a href="{{ url('/admin/login') }}" style="color: {{ $primaryColor }};">{{ url('/admin/login') }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #6c757d;">Email:</td>
                        <td style="padding: 8px 0; font-weight: 600;">{{ $user->email }}</td>
                    </tr>
                </table>
            </div>

            <div style="background: #fff3cd; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <p style="margin: 0; color: #856404; font-size: 14px;">
                    Log in with your existing account. If you've forgotten your password, use the
                    <a href="{{ url('/password/reset') }}" style="color: {{ $primaryColor }}; font-weight: 600;">Forgot Password</a> link to reset it.
                </p>
            </div>
        @endif

        <div style="background: #e8eaf6; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <h4 style="margin: 0 0 10px 0; color: {{ $primaryColor }};">What you can do as {{ $roleName }}:</h4>
            <ul style="margin: 0; padding-left: 20px; color: #5c6bc0; font-size: 14px;">
                <li>View and manage your team</li>
                <li>Add players to your team</li>
                <li>View match schedules and results</li>
                <li>Participate in auctions</li>
            </ul>
        </div>

        <div style="text-align: center;">
            <a href="{{ url('/admin/login') }}"
               style="display: inline-block; background: {{ $primaryColor }}; color: {{ $secondaryColor }}; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600;">
                Login Now
            </a>
        </div>
    </div>

    <div style="text-align: center; padding: 20px; color: #6c757d; font-size: 12px;">
        <p style="margin: 0;">Thank you for joining {{ $tournament->name }}</p>
        <p style="margin: 5px 0 0 0;">{{ config('app.name') }}</p>
    </div>
</body>
</html>
