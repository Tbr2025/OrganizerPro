<?php

declare(strict_types=1);

namespace App\Services\Poster;

use App\Models\AuctionPlayer;
use App\Models\TournamentTemplate;

/**
 * An auction lot, as the placeholder array a TournamentTemplate renders from.
 *
 * The LED wall's own card is screenshotted from the wall, so the hall and the download cannot
 * disagree about what a player looks like on screen. A poster is a different job — it goes to a
 * feed, a story or a printed sheet, is drawn in the drag editor, and needs the same facts in a
 * form the GD renderer understands. This is the translation, in one place, so a poster and a
 * spreadsheet cannot end up disagreeing about what a player was sold for.
 */
class AuctionPosterData
{
    /**
     * @return array<string, string>
     */
    public function forPlayer(AuctionPlayer $auctionPlayer): array
    {
        $auctionPlayer->loadMissing([
            'player.playerType', 'player.battingProfile', 'player.bowlingProfile', 'player.location',
            'soldToTeam', 'pool', 'auction.tournament',
        ]);

        $player = $auctionPlayer->player;
        $auction = $auctionPlayer->auction;
        $tournament = $auction?->tournament;
        $sold = $auctionPlayer->soldToTeam;

        return [
            'player_name' => (string) ($player?->name ?? ''),
            'jersey_name' => (string) ($player?->jersey_name ?? $player?->name ?? ''),
            'jersey_number' => (string) ($player?->jersey_number ?? ''),
            'player_image' => $this->imagePath($player?->image),
            'player_type' => (string) ($player?->playerType?->type ?? ''),
            'batting_style' => (string) ($player?->battingProfile?->style ?? ''),
            'bowling_style' => (string) ($player?->bowlingProfile?->style ?? ''),
            'player_location' => (string) ($player?->location?->name ?? ''),
            'player_age' => $this->age($player?->date_of_birth),

            // Zero-padded, because a lot list reads as a list only when the numbers line up.
            'lot_number' => $auctionPlayer->lot_number
                ? str_pad((string) $auctionPlayer->lot_number, 3, '0', STR_PAD_LEFT)
                : '',
            'pool_name' => (string) ($auctionPlayer->pool?->name ?? ''),
            'base_price' => $this->amount($auctionPlayer->base_price, $auction),

            /*
             * Blank before the hammer falls, rather than a zero or a dash.
             *
             * The renderer hides a placeholder whose value is empty when generating (skipBlanks),
             * so ONE template serves both the "coming up" poster and the "sold" one: the price,
             * the badge and the team simply are not drawn until they are true.
             */
            'sold_price' => $sold ? $this->amount($auctionPlayer->final_price, $auction) : '',
            'sold_status' => $this->status($auctionPlayer),
            'team_name' => (string) ($sold?->name ?? ''),
            'team_logo' => $this->imagePath($sold?->logo),

            'auction_name' => (string) ($auction?->name ?? ''),
            'tournament_name' => (string) ($tournament?->name ?? ''),
            'tournament_logo' => $this->imagePath($tournament?->logo),
        ];
    }

    /** Which of the two auction poster types this template is, if either. */
    public static function isAuctionPoster(TournamentTemplate $template): bool
    {
        return in_array($template->type, [
            TournamentTemplate::TYPE_AUCTION_POSTER,
            TournamentTemplate::TYPE_AUCTION_POSTER_PORTRAIT,
        ], true);
    }

    /**
     * SOLD, UNSOLD, or nothing at all.
     *
     * A player still waiting gets a blank rather than "UNSOLD" — the badge would be a lie on a
     * poster announcing a lot that has not been called yet, and blank is what makes the element
     * disappear instead of stamping the wrong word across the design.
     */
    private function status(AuctionPlayer $auctionPlayer): string
    {
        if ($auctionPlayer->soldToTeam) {
            return 'SOLD';
        }

        // `skipped` included: a player the auction passed over and never came back to finished
        // unsold. Leaving it out printed a blank badge on a poster the Unsold filter had
        // already promised was an unsold one.
        return in_array($auctionPlayer->status, ['unsold', 'passed', 'skipped'], true) ? 'UNSOLD' : '';
    }

    /**
     * Formatted in the auction's own unit, so a poster and the wall agree.
     *
     * An auction configured in points must not have its prices printed as rupees, and one
     * configured in millions must not print 12500000.
     */
    private function amount(mixed $value, ?\App\Models\Auction $auction): string
    {
        if ($value === null || $value === '' || (float) $value <= 0) {
            return '';
        }

        // Auction::formatAmount is the one place that decides how auction money reads —
        // the K/M/B ladder, and whether the unit sits before the figure or after it.
        return $auction
            ? $auction->formatAmount((float) $value, '')
            : number_format((float) $value);
    }

    /**
     * A storage-relative path, which is what the renderer resolves against the public disk.
     *
     * Full URLs are handed back untouched: some logos are stored as absolute URLs and the
     * renderer has its own handling for those.
     */
    private function imagePath(?string $value): string
    {
        return $value ? ltrim($value, '/') : '';
    }

    private function age(mixed $dateOfBirth): string
    {
        if (! $dateOfBirth) {
            return '';
        }

        try {
            return (string) \Carbon\Carbon::parse($dateOfBirth)->age;
        } catch (\Throwable) {
            return '';
        }
    }
}
