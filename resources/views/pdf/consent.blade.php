<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f2937; font-size: 12px; line-height: 1.55; margin: 0; padding: 0; }
        .header { text-align: center; border-bottom: 3px solid #1a56db; padding-bottom: 16px; margin-bottom: 20px; }
        .logos { text-align: center; margin-bottom: 10px; }
        .logos img { max-height: 60px; max-width: 200px; object-fit: contain; vertical-align: middle; margin: 0 14px; }
        .logos .sep { display: inline-block; width: 1px; height: 42px; background: #d1d5db; vertical-align: middle; margin: 0 4px; }
        .header h1 { font-size: 18px; margin: 6px 0 2px; color: #111827; }
        .header .sub { color: #6b7280; font-size: 12px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .meta td { padding: 4px 6px; vertical-align: top; }
        .meta td.k { color: #6b7280; width: 130px; }
        .meta td.v { font-weight: 600; }
        .terms { border: 1px solid #e5e7eb; border-radius: 6px; padding: 14px; white-space: pre-wrap; background: #fafafa; font-size: 11px; color: #374151; }
        .sign { margin-top: 22px; border-top: 1px dashed #9ca3af; padding-top: 14px; }
        .sign .name { font-size: 20px; font-family: 'DejaVu Sans', cursive; color: #111827; }
        .sign .stamp { color: #6b7280; font-size: 11px; margin-top: 4px; }
        .badge { display: inline-block; background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 700; }
        .footer { margin-top: 24px; text-align: center; color: #9ca3af; font-size: 10px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
        .footer .poweredby { margin-top: 6px; }
        .footer .poweredby img { max-height: 22px; max-width: 90px; object-fit: contain; vertical-align: middle; margin: 0 6px; }
        .footer .poweredby .lbl { vertical-align: middle; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        @if($appLogo || $tournamentLogo)
        <div class="logos">
            @if($appLogo)<img src="{{ $appLogo }}" alt="{{ $appName }}">@endif
            @if($appLogo && $tournamentLogo)<span class="sep"></span>@endif
            @if($tournamentLogo)<img src="{{ $tournamentLogo }}" alt="{{ $tournament->name }}">@endif
        </div>
        @endif
        <h1>Registration Consent &amp; Terms Acceptance</h1>
        <div class="sub">{{ $tournament->name }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="k">Applicant</td>
            <td class="v">{{ $registration->player->name ?? $registration->captain_name ?? $signerName }}</td>
        </tr>
        <tr>
            <td class="k">Tournament</td>
            <td class="v">{{ $tournament->name }}</td>
        </tr>
        <tr>
            <td class="k">Status</td>
            <td class="v"><span class="badge">DIGITALLY SIGNED</span></td>
        </tr>
        <tr>
            <td class="k">Signed on</td>
            <td class="v">{{ $signedAt?->format('d M Y, H:i') }} ({{ config('app.timezone') }})</td>
        </tr>
        @if($ip)
        <tr>
            <td class="k">IP address</td>
            <td class="v">{{ $ip }}</td>
        </tr>
        @endif
    </table>

    <h3 style="font-size:13px;margin:0 0 8px;color:#111827;">Terms &amp; Conditions</h3>
    <div class="terms">{{ $content ?: 'No terms content was recorded at the time of signing.' }}</div>

    <div class="sign">
        <div style="color:#6b7280;font-size:11px;margin-bottom:2px;">Digitally signed by</div>
        <div class="name">{{ $signerName }}</div>
        <div class="stamp">
            {{ $signerName }} accepted the above Terms &amp; Conditions on
            {{ $signedAt?->format('d M Y \a\t H:i') }}@if($ip) from IP {{ $ip }}@endif.
        </div>
    </div>

    <div class="footer">
        This document certifies the applicant's electronic acceptance of the terms above.
        <div class="poweredby">
            <span class="lbl">Powered by</span>
            @if($appLogo)<img src="{{ $appLogo }}" alt="{{ $appName }}">@else <strong>{{ $appName }}</strong>@endif
            @if($tournamentLogo)<img src="{{ $tournamentLogo }}" alt="{{ $tournament->name }}">@endif
        </div>
    </div>
</body>
</html>
