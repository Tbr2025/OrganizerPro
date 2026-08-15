<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionPlayer;
use App\Models\Player;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A small, realistic auction to test against locally.
 *
 * A hundred-odd imported players with no travel plans and no clubs is a poor rehearsal: the two
 * fields that change how a lot is judged never appear, so the chips that show them are never
 * exercised and a bug in either is invisible until an event.
 *
 * Local only, by construction. This DELETES auction players, and a command that can delete an
 * evening's work must not be one keystroke away from doing it to a live auction.
 */
class SeedAuctionDemoData extends Command
{
    protected $signature = 'auction:seed-demo {auction : The auction id} {--keep=10 : How many players to leave in it}';

    protected $description = 'Cut a local auction down to a few players and give them travel plans and clubs.';

    /** Clubs a player might currently turn out for. Any strings would do; these read like a league. */
    private const CLUBS = [
        'Colombo CC', 'Galle Gladiators', 'Kandy Kings', 'Jaffna Stallions',
        'Dambulla Sixers', 'Negombo Nomads', 'Matara Mavericks', 'Chilaw Chargers',
    ];

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Refusing to run outside local — this deletes auction players.');

            return self::FAILURE;
        }

        $auction = Auction::find($this->argument('auction'));

        if (! $auction) {
            $this->error('No such auction.');

            return self::FAILURE;
        }

        $keep = max(1, (int) $this->option('keep'));

        /*
         * Keep the ones already furthest along.
         *
         * Ordered by lot number so the survivors are a contiguous run rather than a scatter — a
         * pool with lots 3, 19 and 84 in it looks like a bug when the panel counts "1 of 3".
         */
        $keepIds = $auction->auctionPlayers()
            ->orderByRaw('lot_number IS NULL, lot_number')
            ->limit($keep)
            ->pluck('id');

        $drop = $auction->auctionPlayers()->whereNotIn('id', $keepIds)->pluck('id');

        DB::transaction(function () use ($drop) {
            // Bids first: `auction_bids.auction_player_id` has no cascade, and rows left behind
            // point at a deleted player and keep counting into every bid-derived total.
            AuctionBid::whereIn('auction_player_id', $drop)->delete();
            AuctionPlayer::whereIn('id', $drop)->delete();
        });

        $this->info("Removed {$drop->count()} players; {$keepIds->count()} left in “{$auction->name}”.");

        $rows = $auction->auctionPlayers()->with('player')->get();
        $touched = 0;

        foreach ($rows->values() as $i => $row) {
            $player = $row->player;

            if (! $player) {
                continue;
            }

            /*
             * Every other player gets a travel plan, and every player gets a club.
             *
             * Not all of them travel: the chip has to be exercised BOTH ways, and a screen where
             * every player has one never shows what the layout does when the line is absent.
             */
            $attributes = [
                'playing_team_name_ref' => self::CLUBS[$i % count(self::CLUBS)],
            ];

            if ($i % 2 === 0) {
                $from = now()->addDays(20 + $i)->startOfDay();

                $attributes += [
                    'no_travel_plan' => false,
                    'travel_date_from' => $from,
                    'travel_date_to' => $from->copy()->addDays(10 + ($i % 7)),
                ];
            }

            $player->forceFill($attributes)->save();
            $touched++;
        }

        $this->info("Gave {$touched} players a club, and half of them a travel plan.");
        $this->line('Panel: /admin/organizer/auction/' . $auction->id . '/panel');

        return self::SUCCESS;
    }
}
