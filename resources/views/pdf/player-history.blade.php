<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    {{--
        Standalone document: every style is inline and every image is a data URI, because headless
        Chrome renders this without a session and will not reliably fetch anything over the wire.
        The running footer ("electronically generated … no signature required" + page numbers) is
        NOT here — Chrome draws it from footerHtml() in the controller, which is what repeats it on
        every page.
    --}}
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f2937; font-size: 10px; line-height: 1.45; margin: 0; padding: 0; }

        .header { border-bottom: 3px solid #1a56db; padding-bottom: 10px; margin-bottom: 12px; }
        .logos { text-align: center; margin-bottom: 6px; }
        .logos img { max-height: 58px; max-width: 130px; object-fit: contain; vertical-align: middle; margin: 0 12px; }
        .logos .sep { display: inline-block; width: 1px; height: 40px; background: #d1d5db; vertical-align: middle; }
        .header h1 { font-size: 15px; margin: 4px 0 2px; color: #111827; text-align: center; }
        .header .sub { color: #4b5563; font-size: 10px; text-align: center; }
        .header .scope { color: #6b7280; font-size: 9px; text-align: center; margin-top: 4px; }

        .summary { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .summary td { border: 1px solid #e5e7eb; padding: 5px 7px; text-align: center; background: #f9fafb; }
        .summary .k { display: block; color: #6b7280; font-size: 8px; text-transform: uppercase; letter-spacing: .04em; }
        .summary .v { display: block; font-size: 12px; font-weight: 700; color: #111827; margin-top: 1px; }

        table.rows { width: 100%; border-collapse: collapse; }
        table.rows thead th { background: #1f2937; color: #fff; font-size: 8px; text-transform: uppercase;
            letter-spacing: .04em; padding: 5px 6px; text-align: left; }
        table.rows tbody td { border-bottom: 1px solid #e5e7eb; padding: 4px 6px; vertical-align: middle; }
        table.rows tbody tr:nth-child(even) td { background: #f9fafb; }
        table.rows tr { page-break-inside: avoid; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .muted { color: #6b7280; }
        .email { color: #9ca3af; font-size: 8px; }

        .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 8px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .03em; }
        .badge-auction { background: #d1fae5; color: #065f46; }
        .badge-icon { background: #fef3c7; color: #92400e; }
        .badge-unsold { background: #fee2e2; color: #991b1b; }
        .badge-open { background: #dbeafe; color: #1e40af; }
        .badge-wait { background: #e5e7eb; color: #374151; }

        .note { margin-top: 10px; padding: 6px 8px; border: 1px solid #fcd34d; background: #fffbeb;
            color: #92400e; font-size: 9px; border-radius: 4px; }
        .empty { padding: 24px; text-align: center; color: #6b7280; }
    </style>
</head>
<body>

<div class="header">
    @if($tournamentLogo || $auctionLogo)
        <div class="logos">
            @if($tournamentLogo)<img src="{{ $tournamentLogo }}" alt="{{ $tournament->name }}">@endif
            @if($tournamentLogo && $auctionLogo)<span class="sep"></span>@endif
            @if($auctionLogo)<img src="{{ $auctionLogo }}" alt="{{ $auction->name }}">@endif
        </div>
    @endif

    <h1>Player History Report</h1>
    <div class="sub">
        {{ $tournament->name }}
        &nbsp;&bull;&nbsp;
        {{-- With no auction filter there is no single auction to brand or name, and saying so is
             better than printing one auction's name over another's rows. --}}
        {{ $auction?->name ?? 'All auctions' }}
    </div>
    {{-- Escaped, not raw: the filter line carries the user's own search text. --}}
    <div class="scope">
        Generated {{ implode('  |  ', array_map(
            fn ($zone, $time) => $zones[$zone] . ' ' . $time,
            array_keys($times), $times
        )) }}
    </div>
    @if(count($describe))
        <div class="scope"><strong>Filters:</strong> {{ implode('  •  ', $describe) }}</div>
    @endif
</div>

<table class="summary">
    <tr>
        <td><span class="k">Players</span><span class="v">{{ number_format($summary['players']) }}</span></td>
        <td><span class="k">Sold</span><span class="v">{{ number_format($summary['sold']) }}</span></td>
        <td><span class="k">Icon Players</span><span class="v">{{ number_format($summary['icons']) }}</span></td>
        <td><span class="k">Unsold</span><span class="v">{{ number_format($summary['unsold']) }}</span></td>
        <td><span class="k">Total Spend</span><span class="v">{{ $auction ? $auction->formatAmount($summary['spend']) : format_points($summary['spend']) }}</span></td>
        <td><span class="k">Highest Buy</span><span class="v">{{ $auction ? $auction->formatAmount($summary['highest']) : format_points($summary['highest']) }}</span></td>
        <td><span class="k">Average Buy</span><span class="v">{{ $auction ? $auction->formatAmount($summary['average']) : format_points($summary['average']) }}</span></td>
    </tr>
</table>

@if($rows->isEmpty())
    <div class="empty">No players match these filters.</div>
@else
    <table class="rows">
        <thead>
            <tr>
                <th style="width:16px;">#</th>
                <th>Player</th>
                <th>Auction</th>
                <th>Pool</th>
                <th>How Acquired</th>
                <th>Team</th>
                <th class="num">Price</th>
                @foreach($zones as $zoneLabel)
                    <th>Acquired ({{ $zoneLabel }})</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $row)
                @php
                    [$class, $label] = match (true) {
                        (bool) $row->is_retained => ['badge-icon', $row->acquisition_label],
                        $row->status === 'sold' => ['badge-auction', $row->acquisition_label],
                        $row->status === 'on_auction' => ['badge-open', 'On the block'],
                        in_array($row->status, ['unsold', 'passed', 'skipped'], true) => ['badge-unsold', 'Unsold'],
                        default => ['badge-wait', 'Upcoming'],
                    };
                @endphp
                <tr>
                    <td class="muted">{{ $i + 1 }}</td>
                    <td>
                        <strong>{{ $row->player->name ?? 'Player #' . $row->player_id }}</strong>
                        @if($row->player?->email)
                            <br><span class="email">{{ $row->player->email }}</span>
                        @endif
                    </td>
                    <td class="muted">{{ $row->auction?->name ?? '—' }}</td>
                    <td class="muted">{{ $row->origin_pool?->name ?? '—' }}</td>
                    <td><span class="badge {{ $class }}">{{ $label }}</span></td>
                    <td>{{ $row->holding_team?->name ?? '—' }}</td>
                    <td class="num">{{ $row->price_label }}</td>
                    @foreach(array_keys($zones) as $zone)
                        <td class="muted">{{ $row->event_times[$zone] ?? '—' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($omitted > 0)
    {{-- Said out loud, because a report that stops silently reads as "this is everyone". --}}
    <div class="note">
        This report shows the first {{ number_format($rows->count()) }} of
        {{ number_format($total) }} matching players. {{ number_format($omitted) }}
        {{ Str::plural('row', $omitted) }} {{ $omitted === 1 ? 'was' : 'were' }} omitted — narrow the
        filters (by auction, pool or date) to export the rest.
    </div>
@endif

</body>
</html>
