<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    {{-- Standalone: inline styles, logos as data URIs. The repeating footer comes from
         footerHtml() in the controller, not from here. --}}
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f2937; font-size: 10px; line-height: 1.45; margin: 0; padding: 0; }

        .header { border-bottom: 3px solid #1a56db; padding-bottom: 10px; margin-bottom: 14px; }
        .logos { text-align: center; margin-bottom: 6px; }
        .logos img { max-height: 56px; max-width: 130px; object-fit: contain; vertical-align: middle; margin: 0 12px; }
        .logos .sep { display: inline-block; width: 1px; height: 38px; background: #d1d5db; vertical-align: middle; }
        .header h1 { font-size: 15px; margin: 4px 0 2px; color: #111827; text-align: center; }
        .header .sub { color: #4b5563; font-size: 10px; text-align: center; }
        .header .scope { color: #6b7280; font-size: 9px; text-align: center; margin-top: 4px; }

        .facts { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .facts td { border: 1px solid #e5e7eb; padding: 5px 7px; background: #f9fafb; }
        .facts .k { display: block; color: #6b7280; font-size: 8px; text-transform: uppercase; letter-spacing: .04em; }
        .facts .v { display: block; font-size: 11px; font-weight: 700; color: #111827; margin-top: 1px; }

        h2.section { font-size: 11px; margin: 16px 0 6px; color: #111827; padding-bottom: 3px; border-bottom: 1px solid #e5e7eb; }
        h3.sealed { font-size: 10px; margin: 10px 0 4px; color: #3730a3; }

        table.rows { width: 100%; border-collapse: collapse; }
        table.rows thead th { background: #1f2937; color: #fff; font-size: 8px; text-transform: uppercase;
            letter-spacing: .04em; padding: 4px 6px; text-align: left; }
        table.rows tbody td { border-bottom: 1px solid #e5e7eb; padding: 3.5px 6px; vertical-align: top; }
        table.rows tr { page-break-inside: avoid; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .muted { color: #6b7280; }
        .note { color: #9ca3af; font-size: 8px; }
        .struck { text-decoration: line-through; color: #9ca3af; }

        table.sealed { width: 100%; border-collapse: collapse; background: #f5f3ff; }
        table.sealed th { font-size: 8px; text-transform: uppercase; letter-spacing: .04em; color: #4b5563;
            padding: 3px 6px; text-align: left; border-bottom: 1px solid #ddd6fe; }
        table.sealed td { padding: 3px 6px; border-bottom: 1px solid #ede9fe; }
        .won { color: #065f46; font-weight: 700; font-size: 8px; text-transform: uppercase; }
        .outcome { margin: 4px 0 0; font-size: 9px; color: #374151; }
        .seed { font-size: 8px; color: #6b7280; margin-top: 2px; }
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

    <h1>Player History &mdash; {{ $player->name ?? 'Player #' . $player->id }}</h1>
    <div class="sub">
        {{ $tournament->name }}@if($player->email) &nbsp;&bull;&nbsp; {{ $player->email }} @endif
    </div>
    <div class="scope">
        Generated {{ implode('  |  ', array_map(
            fn ($zone, $time) => $zones[$zone] . ' ' . $time,
            array_keys($times), $times
        )) }}
    </div>
</div>

@forelse($sections as $section)
    @php $row = $section['row']; @endphp

    <h2 class="section">{{ $section['auction']->name }}</h2>

    <table class="facts">
        <tr>
            <td><span class="k">Pool</span><span class="v">{{ $row->origin_pool?->name ?? '—' }}</span></td>
            <td><span class="k">Lot</span><span class="v">{{ $row->lot_number ?? '—' }}</span></td>
            <td><span class="k">Outcome</span><span class="v">{{ $row->acquisition_label ?? ucfirst(str_replace('_', ' ', $row->status)) }}</span></td>
            <td><span class="k">Team</span><span class="v">{{ $row->holding_team?->name ?? '—' }}</span></td>
            <td><span class="k">Price</span><span class="v">{{ $row->price_label }}</span></td>
        </tr>
    </table>

    <table class="rows">
        <thead>
            <tr>
                <th>What happened</th>
                <th>Team</th>
                <th class="num">Amount</th>
                <th>By</th>
                @foreach($zones as $zoneLabel)
                    <th>{{ $zoneLabel }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($section['events'] as $event)
                <tr>
                    <td>
                        {{-- Retracted and undone entries stay in the document, struck through: a
                             trail that quietly drops one cannot be reconciled against the price
                             it moved. --}}
                        <span class="{{ $event['void'] || $event['undone'] ? 'struck' : '' }}">{{ $event['label'] }}</span>
                        @if($event['note'])<span class="note"> · {{ $event['note'] }}</span>@endif
                        @if($event['gap'])<span class="note"> · +{{ $event['gap'] }}s</span>@endif
                    </td>
                    <td class="muted">{{ $event['team']->name ?? '—' }}</td>
                    <td class="num">{{ $event['amount_label'] ?? '—' }}</td>
                    <td class="muted">{{ $event['actor'] ?? '—' }}</td>
                    @foreach(array_keys($zones) as $zone)
                        <td class="muted">{{ $event['times'][$zone] ?? '—' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach($section['rounds'] as $sealed)
        @php $round = $sealed['round']; @endphp
        <h3 class="sealed">
            Sealed round {{ $round->round_number }} — {{ str_replace('_', ' ', $round->state) }}
            @if($sealed['resolution_label']) ({{ $sealed['resolution_label'] }}) @endif
        </h3>
        <table class="sealed">
            <thead>
                <tr>
                    <th>Team</th>
                    <th class="num">Sealed amount</th>
                    <th>State</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sealed['entries'] as $entry)
                    <tr>
                        <td>
                            {{ $entry['team']->name ?? '—' }}
                            @if($round->winner_team_id && $entry['team'] && $round->winner_team_id === $entry['team']->id)
                                <span class="won">won</span>
                            @endif
                        </td>
                        <td class="num">{{ $entry['amount_label'] }}</td>
                        <td class="muted">
                            {{ $entry['state_label'] }}@if($entry['withdrawn_by_admin']) (by the organizer)@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($sealed['winning_label'])
            <p class="outcome">
                Awarded to <strong>{{ $round->winnerTeam->name ?? '—' }}</strong> at
                <strong>{{ $sealed['winning_label'] }}</strong>.
            </p>
        @endif
        @if($sealed['lot'])
            <p class="seed">
                Tie broken by lot ({{ $sealed['lot']['algorithm'] }}), seed {{ $sealed['lot']['seed'] }} —
                recorded so the draw can be re-run and checked.
            </p>
        @endif
    @endforeach
@empty
    <div class="empty">{{ $player->name }} has no auction history in this tournament.</div>
@endforelse

</body>
</html>
