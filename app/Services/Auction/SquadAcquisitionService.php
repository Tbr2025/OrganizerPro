<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Models\ActualTeam;
use App\Models\Auction;
use App\Models\AuctionPlayer;
use Illuminate\Support\Collection;

/**
 * How each player in a squad got there, and what they cost.
 *
 * `players.player_mode` cannot answer this. Selling a player at auction sets it to `retained`
 * as well as keeping one does, so every squad view that read it labelled auction buys
 * "Retained" — and the organizer's team page went further and offered an "Unretain" button for
 * a player who had actually been bought in the room. The honest source is the auction row:
 * `status = sold` + `sold_to_team_id` is a buy, `is_retained` + `team_id` is a keep.
 *
 * Lives here rather than on a controller because both squad views need it and they were
 * drifting: the team manager's page had already been fixed to read the auction row while the
 * organizer's still read player_mode, so the same player was described two different ways
 * depending on who was looking.
 */
class SquadAcquisitionService
{
    public const AUCTION = 'auction';
    public const RETAINED = 'retained';

    /**
     * What this competition calls a player kept by their team before the auction.
     *
     * One place, so a rename is one line rather than a sweep of twenty Blade files. The DATA
     * keeps its own name — `player_mode = 'retained'`, `retained_price`, `is_retained`, every
     * route and request field — because renaming a column to match a label is how one thing
     * ends up with two meanings.
     */
    public const RETAINED_LABEL = 'Icon Player';

    /** And what it calls a player bought in the room. */
    public const AUCTION_LABEL = 'Auction';

    /**
     * The badge a squad list should show.
     *
     * These were the wrong way round: a player bought at auction was labelled "Icon Player" and
     * a player KEPT was labelled "Retained". An icon player is one a team keeps before the
     * auction — that is the whole meaning of the word here — so every squad list has been
     * calling buys icons and keeps something else.
     */
    public static function label(?string $acquisition): ?string
    {
        return match ($acquisition) {
            self::AUCTION => self::AUCTION_LABEL,
            self::RETAINED => self::RETAINED_LABEL,
            default => null,
        };
    }

    /** The label for a kept player, for views that have no acquisition value to hand. */
    public static function retainedLabel(): string
    {
        return self::RETAINED_LABEL;
    }

    /**
     * Stamp `acquisition`, `acquisition_price` and `acquisition_price_label` onto each player.
     *
     * One query for the whole collection, not one per player: these lists are rendered for a
     * full squad and this used to be inside a per-player loop.
     *
     * @param  Collection<int, \App\Models\Player>  $players
     */
    public function attach($players, ActualTeam $team): void
    {
        if ($players->isEmpty() || ! $team->tournament_id) {
            return;
        }

        // The tournament's auction, for formatting money in its own unit (Points, coins,
        // dollars). `$team->auction` is a nullable direct link and is often unset, so the
        // auction is resolved from the tournament instead.
        $auction = Auction::where('tournament_id', $team->tournament_id)->latest('id')->first();

        $rows = AuctionPlayer::query()
            ->whereIn('player_id', $players->pluck('id'))
            ->whereHas('auction', fn ($q) => $q->where('tournament_id', $team->tournament_id))
            ->where(function ($q) use ($team) {
                $q->where(fn ($sold) => $sold->where('status', 'sold')->where('sold_to_team_id', $team->id))
                    ->orWhere(fn ($kept) => $kept->where('is_retained', true)->where('team_id', $team->id));
            })
            ->get()
            ->keyBy('player_id');

        $showValues = $auction === null || $auction->showsSquadValues();
        $showBadge = $auction === null || $auction->showsAcquisitionBadge();

        foreach ($players as $player) {
            $row = $rows->get($player->id);

            if (! $row) {
                $this->clear($player);

                continue;
            }

            $this->stamp($player, $row, (int) $team->id, $auction, $showBadge, $showValues);
        }
    }

    /**
     * The same three fields for a whole tournament at once — two queries, not two per team.
     *
     * attach() resolves the auction and then queries the auction rows, and a public teams page
     * renders every squad in the competition: called per team that is 2N queries for a page that
     * needs 2. The caller hands over a map of team id => that team's players, which it already
     * has loaded, and every squad is stamped from one pass.
     *
     * @param  array<int, \Illuminate\Support\Collection<int, \App\Models\Player>>  $playersByTeam
     */
    public function attachByTeam(array $playersByTeam, int $tournamentId): void
    {
        $playerIds = [];

        foreach ($playersByTeam as $players) {
            foreach ($players as $player) {
                $playerIds[] = $player->id;
            }
        }

        if ($playerIds === []) {
            return;
        }

        $auction = Auction::where('tournament_id', $tournamentId)->latest('id')->first();

        $showValues = $auction === null || $auction->showsSquadValues();
        $showBadge = $auction === null || $auction->showsAcquisitionBadge();

        /*
         * Every buy and every keep in this tournament, in one query. Keyed by the team the row
         * concerns — `sold_to_team_id` for a buy, `team_id` for a keep — so each squad below
         * looks up only its own rows and a player sold to a rival is never stamped onto this
         * team's card.
         */
        $rows = AuctionPlayer::query()
            ->whereIn('player_id', array_unique($playerIds))
            ->whereHas('auction', fn ($q) => $q->where('tournament_id', $tournamentId))
            ->where(function ($q) {
                $q->where(fn ($sold) => $sold->where('status', 'sold')->whereNotNull('sold_to_team_id'))
                    ->orWhere(fn ($kept) => $kept->where('is_retained', true)->whereNotNull('team_id'));
            })
            ->get();

        /*
         * Filed under the team the row concerns, and under BOTH when both columns are populated —
         * that is exactly what attach()'s two-branch WHERE did when asked about each team in
         * turn, and stamp() then decides buy-or-keep from the team it is given. Keying on one
         * column would silently drop a row for the other team.
         */
        $byTeam = [];

        foreach ($rows as $row) {
            if ($row->status === 'sold' && $row->sold_to_team_id !== null) {
                $byTeam[(int) $row->sold_to_team_id][$row->player_id] = $row;
            }

            if ($row->is_retained && $row->team_id !== null) {
                $byTeam[(int) $row->team_id][$row->player_id] = $row;
            }
        }

        foreach ($playersByTeam as $teamId => $players) {
            $teamRows = $byTeam[(int) $teamId] ?? [];

            foreach ($players as $player) {
                $row = $teamRows[$player->id] ?? null;

                if (! $row) {
                    $this->clear($player);

                    continue;
                }

                $this->stamp($player, $row, (int) $teamId, $auction, $showBadge, $showValues);
            }
        }
    }

    /**
     * Write the four acquisition fields onto one player from one auction row.
     *
     * Shared by attach() and attachByTeam() so the two cannot drift — which is the bug this
     * whole service exists to stop, one squad view describing a player differently from another.
     */
    private function stamp($player, AuctionPlayer $row, int $teamId, ?Auction $auction, bool $showBadge, bool $showValues): void
    {
        $bought = $row->status === 'sold' && (int) $row->sold_to_team_id === $teamId;
        $price = (float) ($bought ? $row->final_price : $row->retained_price);

        $player->acquisition = $bought ? self::AUCTION : self::RETAINED;
        /*
         * The badge LABEL is null when the tournament has the badge switched off, exactly as
         * the price label is when values are off — so a view renders what it is given and
         * there is one decision here rather than one per template. `acquisition` itself is
         * left alone: filters, exports and squad arithmetic all read it, and hiding a badge
         * must not change what a player IS.
         */
        $player->acquisition_label = $showBadge ? self::label($player->acquisition) : null;
        $player->acquisition_price = $price;

        /*
         * The price LABEL is null when the auction has values switched off — so a view can
         * render `acquisition_price_label` without also having to know about the setting,
         * and there is one decision instead of one per template. The raw
         * `acquisition_price` is left in place for anything that needs to compute.
         */
        $player->acquisition_price_label = $showValues && $price > 0
            ? ($auction ? $auction->formatAmount($price) : format_points($price))
            : null;
    }

    /**
     * The same three fields, for players whose team is not known to the caller.
     *
     * attach() answers "how did this player join THIS team", which is the question a squad list
     * asks. A players list has no such context — it shows players from every team and every
     * tournament at once — so this resolves each player against their OWN team instead, from
     * `players.actual_team_id`, still in one query for the whole collection.
     *
     * Needed because `players.player_mode` cannot be read as "retained": AuctionSaleService
     * sets it to `retained` when a player is SOLD, so the column really means "claimed by a
     * team", and every list that rendered it as the word "Retained" was mislabelling every
     * purchase — some of them alongside a Remove Retention button that would have stripped one.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Player>|\Illuminate\Support\Enumerable  $players
     */
    public function attachForOwnTeams($players): void
    {
        if ($players->isEmpty()) {
            return;
        }

        $rows = AuctionPlayer::query()
            ->whereIn('player_id', $players->pluck('id'))
            ->where(fn ($q) => $q->where('status', 'sold')->orWhere('is_retained', true))
            ->with('auction')
            // Newest last so keyBy keeps the most recent row for a player who has been through
            // more than one auction.
            ->orderBy('id')
            ->get()
            ->groupBy('player_id');

        foreach ($players as $player) {
            $candidates = $rows->get($player->id);

            if (! $candidates) {
                $this->clear($player);

                continue;
            }

            $teamId = $player->actual_team_id ? (int) $player->actual_team_id : null;

            /*
             * Prefer the row for the team the player is actually on. A player sold in one
             * auction and retained by a different team in another has two rows, and picking the
             * wrong one describes them as belonging somewhere they do not.
             */
            $row = $candidates->last(function ($candidate) use ($teamId) {
                if ($teamId === null) {
                    return true;
                }

                return (int) $candidate->sold_to_team_id === $teamId
                    || (int) $candidate->team_id === $teamId;
            }) ?? $candidates->last();

            $bought = $row->status === 'sold' && $row->sold_to_team_id !== null;
            $price = (float) ($bought ? $row->final_price : $row->retained_price);
            $auction = $row->auction;

            $player->acquisition = $bought ? self::AUCTION : self::RETAINED;
            // Null when the tournament has the badge off — see attach() for why.
            $player->acquisition_label = ($auction === null || $auction->showsAcquisitionBadge())
                ? self::label($player->acquisition)
                : null;
            $player->acquisition_price = $price;
            $player->acquisition_price_label = ($auction === null || $auction->showsSquadValues()) && $price > 0
                ? ($auction ? $auction->formatAmount($price) : format_points($price))
                : null;
        }
    }

    /** A player with no auction row is neither bought nor kept — they simply joined. */
    private function clear($player): void
    {
        $player->acquisition = null;
        $player->acquisition_label = null;
        $player->acquisition_price = null;
        $player->acquisition_price_label = null;
    }
}
