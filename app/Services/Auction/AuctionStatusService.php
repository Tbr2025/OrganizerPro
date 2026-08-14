<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\Auction;
use App\Models\AuctionPlayer;
use Illuminate\Support\Collection;

/**
 * Where each player stands in the auction — sold, unsold, or still to come.
 *
 * Both player lists needed this and neither had it. The team manager's list was worse than
 * missing it: it excluded players whose `player_mode` is `retained`, and AuctionSaleService sets
 * that on a SALE as well as on a keep — so every player who had actually been sold vanished from
 * the list, which is the opposite of what a manager wants to see during an auction.
 *
 * `auction_players.is_retained` is the flag that tells the two apart, and it is the one this uses.
 */
class AuctionStatusService
{
    /** Sold, unsold, or waiting — the three a room actually asks about. */
    public const STATUS_SOLD = 'sold';
    public const STATUS_UNSOLD = 'unsold';
    public const STATUS_UPCOMING = 'upcoming';

    /** @return array<string, string> value => label, for the filter dropdown. */
    public static function options(): array
    {
        return [
            self::STATUS_SOLD => 'Sold',
            self::STATUS_UNSOLD => 'Unsold',
            self::STATUS_UPCOMING => 'Upcoming / waiting',
        ];
    }

    /** The auction a tournament's players are being bought in, if it has one. */
    public function auctionFor(?int $tournamentId): ?Auction
    {
        if (! $tournamentId) {
            return null;
        }

        return Auction::where('tournament_id', $tournamentId)->orderByDesc('id')->first();
    }

    /**
     * Player ids at a given status, for filtering a list that is otherwise about players.
     *
     * `on_auction` counts as upcoming: a player on the block has not been decided yet, and a
     * filter that dropped them for the minute they are up would blink a name out of the list
     * mid-lot.
     *
     * @return list<int>
     */
    public function playerIdsWithStatus(Auction $auction, string $status): array
    {
        $rows = AuctionPlayer::where('auction_id', $auction->id)->where('is_retained', false);

        $rows = match ($status) {
            self::STATUS_SOLD => $rows->where('status', 'sold'),
            self::STATUS_UNSOLD => $rows->whereIn('status', ['unsold', 'skipped']),
            self::STATUS_UPCOMING => $rows->whereIn('status', ['waiting', 'on_auction']),
            default => $rows,
        };

        return $rows->pluck('player_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Player ids that are RETAINED — pre-kept, never on the block. */
    public function retainedPlayerIds(Auction $auction): array
    {
        return AuctionPlayer::where('auction_id', $auction->id)
            ->where('is_retained', true)
            ->pluck('player_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Hang each player's auction row on them: status, buying team and price.
     *
     * One query for the page, not one per player. These lists paginate at twenty and this is a
     * display concern — it must not turn a list into twenty lookups.
     *
     * @param  Collection<int, \App\Models\Player>  $players
     */
    public function attach(Collection $players, ?Auction $auction): void
    {
        if (! $auction || $players->isEmpty()) {
            $players->each(function ($player) {
                $player->auction_status = null;
                $player->auction_price = null;
                $player->auction_team = null;
            });

            return;
        }

        $rows = AuctionPlayer::where('auction_id', $auction->id)
            ->whereIn('player_id', $players->pluck('id')->all())
            ->with('soldToTeam:id,name,team_logo')
            ->get()
            ->keyBy('player_id');

        $players->each(function ($player) use ($rows) {
            $row = $rows->get($player->id);

            if (! $row) {
                $player->auction_status = null;
                $player->auction_price = null;
                $player->auction_team = null;

                return;
            }

            $player->auction_status = match (true) {
                (bool) $row->is_retained => 'retained',
                $row->status === 'sold' => self::STATUS_SOLD,
                in_array($row->status, ['unsold', 'skipped'], true) => self::STATUS_UNSOLD,
                default => self::STATUS_UPCOMING,
            };

            $player->auction_price = $row->status === 'sold'
                ? (float) $row->final_price
                : ((bool) $row->is_retained ? (float) $row->retained_price : null);

            $player->auction_team = $row->soldToTeam;
            $player->auction_lot = $row->lot_number;
        });
    }
}
