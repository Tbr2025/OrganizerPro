<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\AuctionPlayer;
use App\Models\Player;
use App\Services\Auction\AuctionPoolService;
use Illuminate\Console\Command;

/**
 * Price retained players who were recorded with no retention cost at all.
 *
 * A blank retention price used to be stored as 0, so those players cost their team
 * nothing and the team has been bidding with a purse that is too large.
 *
 * Deliberately a command rather than a migration. Stamping a value onto live rows moves
 * every affected team's spend, remaining budget, reserve and bid ceiling: on a *running*
 * auction that recomputes exclusions on the next poll and can lock teams out mid-lot,
 * and on a completed one it can push settled purchases retroactively over budget. A 0
 * also cannot be told apart from a deliberate free retention, so any blanket rule is a
 * guess. The organizer should see what would change, then decide.
 */
class BackfillRetainedPrices extends Command
{
    protected $signature = 'auction:backfill-retained-prices
        {auction? : Auction id; omit to list every auction with unpriced retentions}
        {--source=player : Where to take the price from — `player` (the player\'s own retained_value, falling back to the auction default) or `default` (always the auction default)}
        {--dry-run : Show what would change and write nothing}
        {--force : Allow auctions that are not still `scheduled`}';

    protected $description = 'Give retained players with no retention price a real one.';

    public function handle(AuctionPoolService $pools): int
    {
        $auctionId = $this->argument('auction');

        if ($auctionId === null) {
            return $this->listCandidates();
        }

        $auction = Auction::withoutGlobalScopes()->find($auctionId);

        if (! $auction) {
            $this->error("No auction with id {$auctionId}.");

            return self::FAILURE;
        }

        if ($auction->status !== 'scheduled' && ! $this->option('force')) {
            $this->error("Auction {$auction->id} is `{$auction->status}`, not `scheduled`.");
            $this->line('Repricing a live or finished auction moves purses that teams are bidding against.');
            $this->line('Re-run with --force if that is really what you want.');

            return self::FAILURE;
        }

        $rows = $this->unpricedRows($auction->id);

        if ($rows->isEmpty()) {
            $this->info("Nothing to do: every retained player in auction {$auction->id} already has a price.");

            return self::SUCCESS;
        }

        $players = Player::withoutGlobalScopes()
            ->whereIn('id', $rows->pluck('player_id')->filter())
            ->get(['id', 'name', 'retained_value'])
            ->keyBy('id');

        $useDefault = $this->option('source') === 'default';
        $plan = [];

        foreach ($rows as $row) {
            $player = $players->get($row->player_id);

            $price = $useDefault
                ? $auction->defaultRetainedValue()
                : $pools->resolveRetainedPrice($auction, $player, null, null);

            $plan[] = [
                $row->id,
                $player?->name ?? "Player #{$row->player_id}",
                $auction->formatAmount($row->retained_price ?? 0, '0'),
                $auction->formatAmount($price),
            ];
        }

        $this->table(['Row', 'Player', 'Now', 'Would become'], $plan);
        $this->newLine();
        $this->showPurseImpact($auction, $pools, $rows);

        if ($this->option('dry-run')) {
            $this->comment('Dry run — nothing was written.');

            return self::SUCCESS;
        }

        if (! $this->confirm(sprintf('Apply these %d price(s)?', count($plan)), false)) {
            $this->comment('Cancelled. Nothing was written.');

            return self::SUCCESS;
        }

        $applied = 0;

        foreach ($rows as $row) {
            $player = $players->get($row->player_id);

            $row->update([
                'retained_price' => $useDefault
                    ? $auction->defaultRetainedValue()
                    : $pools->resolveRetainedPrice($auction, $player, null, null),
            ]);
            $applied++;
        }

        $this->info("Priced {$applied} retained player(s) in auction {$auction->id}.");

        return self::SUCCESS;
    }

    /** Retained rows charging their team nothing. */
    private function unpricedRows(int $auctionId)
    {
        return AuctionPlayer::withoutGlobalScopes()
            ->where('auction_id', $auctionId)
            ->where('is_retained', true)
            ->where(fn ($q) => $q->whereNull('retained_price')->orWhere('retained_price', 0))
            ->orderBy('id')
            ->get();
    }

    private function listCandidates(): int
    {
        $auctions = Auction::withoutGlobalScopes()->orderBy('id')->get(['id', 'name', 'status']);
        $rows = [];

        foreach ($auctions as $auction) {
            $count = $this->unpricedRows($auction->id)->count();

            if ($count > 0) {
                $rows[] = [$auction->id, $auction->name, $auction->status, $count];
            }
        }

        if ($rows === []) {
            $this->info('No auction has retained players without a price.');

            return self::SUCCESS;
        }

        $this->table(['Auction', 'Name', 'Status', 'Unpriced retentions'], $rows);
        $this->line('Run `auction:backfill-retained-prices <id> --dry-run` to see what one would change.');

        return self::SUCCESS;
    }

    /** What this would do to each affected team's purse. */
    private function showPurseImpact(Auction $auction, AuctionPoolService $pools, $rows): void
    {
        $teamIds = $rows->pluck('team_id')->filter()->unique();

        if ($teamIds->isEmpty()) {
            return;
        }

        $impact = [];

        foreach ($teamIds as $teamId) {
            $state = $pools->teamPurseState($auction, (int) $teamId);
            $impact[] = [
                $teamId,
                $auction->formatAmount($state['allocated']),
                $auction->formatAmount($state['retained_spent']),
                $auction->formatAmount($state['remaining']),
                $auction->formatAmount($state['max_bid_allowed']),
            ];
        }

        $this->line('Purses as they stand now (all of these will fall):');
        $this->table(['Team', 'Allocated', 'Retained', 'Remaining', 'Max bid'], $impact);
    }
}
