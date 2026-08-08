<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Auction;
use App\Services\Auction\AuctionPoolService;

/**
 * Everything an auction knows about itself right now, as a spreadsheet.
 *
 * This is a rescue tool. If the room hits a problem mid-auction — a screen that will not
 * come back, a result nobody trusts, a decision that has to be settled off the system —
 * the organizer needs the state OUT, in something they can read on any laptop in the
 * hall, without waiting for anyone to look at a database.
 *
 * So it is deliberately built to work when things are broken:
 *
 *  - Every figure is read fresh at the moment of export. Nothing is cached.
 *  - It never writes. Exporting cannot make a bad situation worse.
 *  - A player with no team, no price or no pool still gets a row. Missing data is what
 *    you are exporting to investigate, so it must not be what stops the export.
 *  - Money is written as numbers, so the totals can be checked with a SUM in the
 *    spreadsheet rather than taken on trust.
 */
class AuctionSnapshotExport
{
    public function __construct(private readonly AuctionPoolService $pools)
    {
    }

    public function filename(Auction $auction): string
    {
        $name = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $auction->name) ?: 'auction';

        return trim($name, '-') . '-' . $auction->id . '-' . now()->format('Y-m-d-His') . '.xlsx';
    }

    public function build(Auction $auction): XlsxWriter
    {
        return (new XlsxWriter())
            ->addSheet('Players', $this->playerRows($auction))
            ->addSheet('Teams', $this->teamRows($auction))
            ->addSheet('Summary', $this->summaryRows($auction));
    }

    /**
     * One row per player in the auction, sold or not.
     *
     * Unsold and waiting players are included on purpose: "who is left" is usually the
     * first question when a run has to be restarted or finished by hand.
     */
    private function playerRows(Auction $auction): array
    {
        $rows = [[
            'Lot', 'Player', 'Pool', 'Status', 'Base Price',
            'Sold To', 'Final Price', 'Current Bid', 'Leading Team', 'Retained', 'Retained Value',
        ]];

        $players = $auction->auctionPlayers()
            ->with(['player:id,name', 'pool:id,name', 'soldToTeam:id,name', 'currentBidTeam:id,name'])
            ->orderByRaw('COALESCE(lot_number, 999999)')
            ->orderBy('id')
            ->get();

        foreach ($players as $ap) {
            $rows[] = [
                $ap->lot_number !== null ? (int) $ap->lot_number : '',
                $ap->player->name ?? '(player record missing)',
                $ap->pool->name ?? '',
                (string) $ap->status,
                (float) ($ap->base_price ?? 0),
                $ap->soldToTeam->name ?? '',
                $ap->final_price !== null ? (float) $ap->final_price : '',
                (float) ($ap->current_price ?? 0),
                $ap->currentBidTeam->name ?? '',
                $ap->is_retained ? 'yes' : '',
                $ap->retained_price !== null ? (float) $ap->retained_price : '',
            ];
        }

        return $rows;
    }

    /**
     * One row per participating team: what they were given, what they have spent and on
     * what, and what is left.
     *
     * Every figure comes from AuctionPoolService::teamPurseState(), the same source the
     * panel and the bidding page read, so a disputed number in the hall and a number in
     * this file cannot disagree.
     */
    private function teamRows(Auction $auction): array
    {
        $rows = [[
            'Team', 'Budget', 'Retained Spend', 'Auction Spend', 'Total Spent', 'Remaining',
            'Players Bought', 'Retained Count', 'Squad Size', 'Slots Required', 'Slots Remaining',
            'Reserve Held', 'Max Bid Allowed',
        ]];

        foreach ($this->pools->participatingTeams($auction) as $team) {
            $purse = $this->pools->teamPurseState($auction, $team->id);

            $rows[] = [
                (string) $team->name,
                $this->money($purse['allocated'] ?? null),
                $this->money($purse['retained_spent'] ?? null),
                $this->money($purse['auction_spent'] ?? null),
                $this->money($purse['spent'] ?? null),
                $this->money($purse['remaining'] ?? null),
                (int) ($purse['slots_filled'] ?? 0) - (int) ($purse['retained_count'] ?? 0),
                (int) ($purse['retained_count'] ?? 0),
                (int) ($purse['slots_filled'] ?? 0),
                (int) ($purse['slots_required'] ?? 0),
                (int) ($purse['slots_remaining'] ?? 0),
                $this->money($purse['reserve'] ?? null),
                $this->money($purse['max_bid_allowed'] ?? null),
            ];
        }

        return $rows;
    }

    /** The state of the run itself, so the file explains its own context. */
    private function summaryRows(Auction $auction): array
    {
        $players = $auction->auctionPlayers()->get(['status', 'final_price']);

        $count = fn (string $status) => $players->where('status', $status)->count();

        return [
            ['Field', 'Value'],
            ['Auction', (string) $auction->name],
            ['Auction ID', (int) $auction->id],
            ['Tournament', $auction->tournament->name ?? ''],
            ['Exported At', now()->format('Y-m-d H:i:s')],
            ['Status', (string) $auction->status],
            ['Bidding Mode', (string) $auction->open_bid_mode],
            ['Bid Phase', (string) $auction->bid_type],
            ['Budget Per Team', $this->money($auction->max_budget_per_team)],
            ['Sealed Bid Starts At', $this->money($auction->closed_bid_starts_at)],
            ['Sealed Bid Step', $this->money($auction->closedBidStep())],
            ['Players Total', $players->count()],
            ['Sold', $count('sold')],
            ['Unsold', $count('unsold')],
            ['Skipped', $count('skipped')],
            ['Waiting', $count('waiting')],
            ['On Auction', $count('on_auction')],
            ['Total Sale Value', (float) $players->sum(fn ($p) => (float) ($p->final_price ?? 0))],
        ];
    }

    /**
     * An open tournament has no budget, and AuctionPoolService represents "no ceiling" as
     * PHP_FLOAT_MAX. Writing 1.8e308 into a spreadsheet is worse than writing nothing.
     */
    private function money(mixed $value): float|string
    {
        if ($value === null || ! is_numeric($value)) {
            return '';
        }

        $value = (float) $value;

        return $value >= PHP_FLOAT_MAX / 2 ? 'no limit' : $value;
    }
}
