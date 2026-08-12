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
     * The badge a squad list should show. "Icon Player" for someone bought in the room,
     * "Retained" for someone kept — the two facts squad views most need to tell apart.
     */
    public static function label(?string $acquisition): ?string
    {
        return match ($acquisition) {
            self::AUCTION => 'Icon Player',
            self::RETAINED => 'Retained',
            default => null,
        };
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

        foreach ($players as $player) {
            $row = $rows->get($player->id);

            if (! $row) {
                $player->acquisition = null;
                $player->acquisition_price = null;
                $player->acquisition_price_label = null;
                $player->acquisition_label = null;

                continue;
            }

            $bought = $row->status === 'sold' && (int) $row->sold_to_team_id === (int) $team->id;
            $price = (float) ($bought ? $row->final_price : $row->retained_price);

            $player->acquisition = $bought ? self::AUCTION : self::RETAINED;
            $player->acquisition_label = self::label($player->acquisition);
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
    }
}
