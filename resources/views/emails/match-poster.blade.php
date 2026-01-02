<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upcoming Match</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #1f2937; color: #ffffff;">
    <table role="presentation" style="width: 100%; max-width: 600px; margin: 0 auto; background-color: #111827;">
        {{-- Header --}}
        <tr>
            <td style="padding: 30px 20px; text-align: center; background: linear-gradient(135deg, #eab308 0%, #ca8a04 100%);">
                <h1 style="margin: 0; color: #111827; font-size: 24px;">{{ $tournament->name }}</h1>
            </td>
        </tr>

        {{-- Main Content --}}
        <tr>
            <td style="padding: 30px 20px;">
                <h2 style="margin: 0 0 20px 0; text-align: center; color: #ffffff; font-size: 18px;">
                    Upcoming Match Alert!
                </h2>

                {{-- Match Card --}}
                <table role="presentation" style="width: 100%; background-color: #1f2937; border-radius: 8px; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 20px; text-align: center;">
                            {{-- Teams --}}
                            <table role="presentation" style="width: 100%;">
                                <tr>
                                    <td style="width: 40%; text-align: center; vertical-align: top;">
                                        <p style="margin: 0; font-size: 20px; font-weight: bold; color: #ffffff;">
                                            {{ $match->teamA?->name ?? 'TBA' }}
                                        </p>
                                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #9ca3af;">
                                            ({{ $match->teamA?->short_name ?? 'TBA' }})
                                        </p>
                                    </td>
                                    <td style="width: 20%; text-align: center; vertical-align: middle;">
                                        <span style="display: inline-block; padding: 10px 15px; background-color: #eab308; color: #111827; font-weight: bold; border-radius: 50%; font-size: 14px;">
                                            VS
                                        </span>
                                    </td>
                                    <td style="width: 40%; text-align: center; vertical-align: top;">
                                        <p style="margin: 0; font-size: 20px; font-weight: bold; color: #ffffff;">
                                            {{ $match->teamB?->name ?? 'TBA' }}
                                        </p>
                                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #9ca3af;">
                                            ({{ $match->teamB?->short_name ?? 'TBA' }})
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                {{-- Match Details --}}
                <table role="presentation" style="width: 100%; background-color: #374151; border-radius: 8px;">
                    <tr>
                        <td style="padding: 20px;">
                            <table role="presentation" style="width: 100%;">
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #4b5563;">
                                        <span style="color: #9ca3af;">Date:</span>
                                        <span style="color: #ffffff; font-weight: bold; float: right;">
                                            {{ $match->match_date->format('l, F d, Y') }}
                                        </span>
                                    </td>
                                </tr>
                                @if($match->match_time)
                                <tr>
                                    <td style="padding: 10px 0; border-bottom: 1px solid #4b5563;">
                                        <span style="color: #9ca3af;">Time:</span>
                                        <span style="color: #ffffff; font-weight: bold; float: right;">
                                            {{ $match->match_time->format('h:i A') }}
                                        </span>
                                    </td>
                                </tr>
                                @endif
                                @if($match->ground)
                                <tr>
                                    <td style="padding: 10px 0;">
                                        <span style="color: #9ca3af;">Venue:</span>
                                        <span style="color: #ffffff; font-weight: bold; float: right;">
                                            {{ $match->ground->name }}
                                        </span>
                                    </td>
                                </tr>
                                @endif
                            </table>
                        </td>
                    </tr>
                </table>

                {{-- CTA Button --}}
                <table role="presentation" style="width: 100%; margin-top: 30px;">
                    <tr>
                        <td style="text-align: center;">
                            <a href="{{ route('public.match.show', $match->slug) }}"
                               style="display: inline-block; padding: 15px 30px; background-color: #eab308; color: #111827; text-decoration: none; font-weight: bold; border-radius: 8px;">
                                View Match Details
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- Footer --}}
        <tr>
            <td style="padding: 20px; text-align: center; background-color: #0f172a; border-top: 1px solid #374151;">
                <p style="margin: 0; color: #6b7280; font-size: 12px;">
                    This email was sent by {{ config('app.name') }}
                </p>
                <p style="margin: 10px 0 0 0; color: #6b7280; font-size: 12px;">
                    Powered by OrganizerPro
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
