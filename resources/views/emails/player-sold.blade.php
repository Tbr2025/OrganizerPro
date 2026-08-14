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

        /*
         * The text colour is DERIVED from the background it sits on, not taken from the
         * brand's secondary colour.
         *
         * The header and the button paint themselves the tournament's primary colour and then
         * wrote their text in its secondary — two brand colours stacked with nothing
         * guaranteeing they contrast. A tournament whose colours are both dark rendered
         * "You've Been Selected!" in navy on navy: present in the markup, invisible to the
         * reader, and equally invisible on the button.
         *
         * sRGB relative luminance, the same measure WCAG uses, with a threshold that keeps
         * white on anything but genuinely light backgrounds.
         */
        $readableOn = function (?string $hex) {
            $hex = ltrim((string) $hex, '#');

            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }

            // An unparseable colour is not worth guessing at; white is safe on the dark
            // headers these emails have always had.
            if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
                return '#ffffff';
            }

            $luminance = (0.2126 * hexdec(substr($hex, 0, 2))
                + 0.7152 * hexdec(substr($hex, 2, 2))
                + 0.0722 * hexdec(substr($hex, 4, 2))) / 255;

            return $luminance > 0.6 ? '#111827' : '#ffffff';
        };

        $onPrimary = $readableOn($primaryColor);
    @endphp
    <div style="background: {{ $primaryColor }}; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <img src="{{ $tournament?->settings?->logo_url ?? url('/images/logo/logo.png') }}" alt="{{ $tournament?->name ?? config('app.name') }}" style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; display: block; object-fit: contain; background: white; padding: 8px;">
        <h1 style="color: {{ $onPrimary }}; margin: 0; font-size: 24px;">You've Been Selected!</h1>
    </div>

    <div style="background: #f8f9fa; padding: 30px; border: 1px solid #e9ecef; border-top: none;">
        {{-- The stamp, said once at the top.
             The poster below carries it as artwork; this is the plain-HTML version for the
             clients that strip images and for the preview pane, where the first line should be
             the news rather than "Dear …". --}}
        <div style="text-align: center; margin: 0 0 22px 0;">
            <span style="display: inline-block; padding: 10px 34px; border-radius: 999px;
                         background: #15803d; color: #ffffff; font-size: 22px; font-weight: 800;
                         letter-spacing: 3px; text-transform: uppercase;">
                Sold
            </span>
            <div style="margin-top: 10px; font-size: 15px; color: #15803d; font-weight: 700;">
                {{ $player->name }} &rarr; {{ $team->name }} &middot; {{ $auction->formatAmount($finalPrice) }}
            </div>
        </div>

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
                    {{-- The auction's own formatter: the K/M/B ladder and the unit this auction
                         calls its money. `number_format` printed 2,800,000 in an email whose
                         sender spent the evening saying "2.8M Points" on every screen in the
                         hall — the same figure, unrecognisable. --}}
                    <td style="padding: 8px 0; font-weight: 600; color: {{ $primaryColor }};">{{ $auction->formatAmount($finalPrice) }}</td>
                </tr>
                @if($tournament)
                <tr>
                    <td style="padding: 8px 0; color: #6c757d;">Tournament:</td>
                    <td style="padding: 8px 0;">{{ $tournament->name }}</td>
                </tr>
                @endif
            </table>
        </div>

        {{-- The sold poster, in the body.
             An attachment alone is a file somebody has to go and open. The picture in the message
             is the thing a player screenshots and puts in a group chat, which is the whole point
             of drawing one. It is attached as well, because inline images are what some clients
             strip and attachments are what the rest hide behind a paperclip.

             `$posterCid` is null whenever no poster could be rendered — no template drawn, or the
             render failed — and the email then reads exactly as it did before. --}}
        @if(! empty($posterCid))
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="{{ $message->embed($posterPath) }}" alt="{{ $player->name }} — sold to {{ $team->name }}"
                 style="max-width: 100%; height: auto; border-radius: 8px;">
        </div>
        @endif

        <div style="background: #d4edda; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <p style="margin: 0; color: #155724; font-size: 14px;">
                Welcome to <strong>{{ $team->name }}</strong>! Get ready to give your best performance. Stay tuned for further updates and match schedules.
            </p>
        </div>

        @if($tournament?->slug)
        <div style="text-align: center;">
            <a href="{{ route('public.tournament.show', $tournament->slug) }}"
               style="display: inline-block; background: {{ $primaryColor }}; color: {{ $onPrimary }}; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600;">
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
